<?php
declare(strict_types=1);

namespace AIO\Auth;

use Base32\Base32;
use EasyTOTP\Factory;
use EasyTOTP\TOTPInterface;
use EasyTOTP\TOTPValidResultInterface;

/**
 * Thin wrapper around rullzer/easytotp (the same library the reference app
 * nextcloud/twofactor_totp uses).
 */
readonly class TotpService {
    private const int PERIOD = 30;
    private const int DIGITS = 6;
    // Accepted clock-skew window, in 30s steps. 1 = ±30s.
    private const int DRIFT = 1;
    private const string BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** 32 base32 chars = 160 bits, per RFC 4226; no padding, so it drops straight into an otpauth URI. */
    public function generateSecret() : string {
        $secret = '';
        for ($i = 0; $i < 32; $i++) {
            $secret .= self::BASE32_ALPHABET[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Build otpauth:// provisioning URI for authenticator apps/QR-codes.
     */
    public function getProvisioningUri(string $secret, string $label, string $issuer) : string {
        $name = rawurlencode($issuer) . ':' . rawurlencode($label);
        return "otpauth://totp/{$name}?secret={$secret}&issuer=" . rawurlencode($issuer)
            . '&algorithm=SHA1&period=' . self::PERIOD . '&digits=' . self::DIGITS;
    }

    /**
     * Verify a code. Returns [bool $ok, ?int $matchedCounter]; the matched
     * counter is meant to be persisted and passed back as $lastCounter on the
     * next verify, so a valid code cannot be reused inside its own window (RFC 6238).
     *
     * easytotp verifies against the raw HMAC key, so the base32 secret is
     * decoded first.
     *
     * @return array{0: bool, 1: int|null}
     */
    public function verify(string $secret, string $code, ?int $lastCounter = null) : array {
        $code = trim($code);
        if ($secret === '' || !preg_match('#^\d{' . self::DIGITS . '}$#', $code)) {
            return [false, null];
        }
        $totp = Factory::getTOTP(Base32::decode($secret), self::PERIOD, self::DIGITS, 0, TOTPInterface::HASH_SHA1);
        $result = $totp->verify($code, self::DRIFT, $lastCounter);
        if ($result instanceof TOTPValidResultInterface) {
            return [true, $result->getCounter()];
        }
        return [false, null];
    }
}
