<?php

declare(strict_types=1);

namespace AIO\Tests;

use AIO\Auth\TotpService;
use Base32\Base32;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the pure TOTP wrapper. TotpService touches no filesystem, so
 * these run without the AIO data directory. easytotp reads the wall clock via
 * its own TimeService, so codes are computed relative to the current window
 * rather than from fixed RFC vectors.
 */
final class TotpServiceTest extends TestCase {
    private TotpService $totp;

    protected function setUp(): void {
        $this->totp = new TotpService();
    }

    /** Compute the authenticator code the same way any TOTP app does. */
    private function codeFor(string $secret, int $offsetSteps = 0): string {
        $key = Base32::decode($secret);
        $counter = intdiv(time(), 30) + $offsetSteps;
        $hash = hash_hmac('sha1', pack('J', $counter), $key, true);
        $offset = ord($hash[19]) & 0xf;
        $binary = (unpack('N', substr($hash, $offset, 4))[1]) & 0x7fffffff;
        return str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function testGeneratedSecretIs32Base32Chars(): void {
        $secret = $this->totp->generateSecret();
        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('#^[A-Z2-7]{32}$#', $secret);
    }

    public function testGeneratedSecretsAreRandom(): void {
        $this->assertNotSame($this->totp->generateSecret(), $this->totp->generateSecret());
    }

    public function testProvisioningUriShape(): void {
        $uri = $this->totp->getProvisioningUri('JBSWY3DPEHPK3PXP', 'admin', 'Nextcloud AIO');
        $this->assertStringStartsWith('otpauth://totp/Nextcloud%20AIO:admin?', $uri);
        $this->assertStringContainsString('secret=JBSWY3DPEHPK3PXP', $uri);
        $this->assertStringContainsString('issuer=Nextcloud%20AIO', $uri);
        $this->assertStringContainsString('algorithm=SHA1', $uri);
        $this->assertStringContainsString('period=30', $uri);
        $this->assertStringContainsString('digits=6', $uri);
    }

    public function testVerifyAcceptsACurrentCode(): void {
        $secret = $this->totp->generateSecret();
        [$ok, $counter] = $this->totp->verify($secret, $this->codeFor($secret));
        $this->assertTrue($ok);
        $this->assertIsInt($counter);
        $this->assertGreaterThan(0, $counter);
    }

    public function testVerifyReturnsMatchedCounterForReplayProtection(): void {
        $secret = $this->totp->generateSecret();
        [$ok, $counter] = $this->totp->verify($secret, $this->codeFor($secret));
        $this->assertTrue($ok);
        // Passing the matched counter back as lastCounter must reject the same code.
        [$replayOk] = $this->totp->verify($secret, $this->codeFor($secret), $counter);
        $this->assertFalse($replayOk);
    }

    public function testVerifyToleratesTheAdjacentWindow(): void {
        $secret = $this->totp->generateSecret();
        // The previous window's code is within the ±1 drift and must still be accepted.
        [$ok] = $this->totp->verify($secret, $this->codeFor($secret, -1));
        $this->assertTrue($ok);
    }

    public function testVerifyRejectsCodeTwoWindowsAway(): void {
        $secret = $this->totp->generateSecret();
        [$ok] = $this->totp->verify($secret, $this->codeFor($secret, 2));
        $this->assertFalse($ok);
    }

    public function testVerifyRejectsWrongCode(): void {
        $secret = $this->totp->generateSecret();
        $current = $this->codeFor($secret);
        $wrong = $current === '000000' ? '111111' : '000000';
        [$ok, $counter] = $this->totp->verify($secret, $wrong);
        $this->assertFalse($ok);
        $this->assertNull($counter);
    }

    /** Malformed input must be rejected before any crypto runs. */
    public function testVerifyRejectsMalformedInput(): void {
        $secret = $this->totp->generateSecret();
        foreach (['', '12345', '1234567', 'abcdef', '12 345'] as $bad) {
            [$ok] = $this->totp->verify($secret, $bad);
            $this->assertFalse($ok, "expected '$bad' to be rejected");
        }
    }

    public function testVerifyRejectsWhenSecretEmpty(): void {
        [$ok] = $this->totp->verify('', '123456');
        $this->assertFalse($ok);
    }
}
