<?php
declare(strict_types=1);

namespace AIO\Auth;

use AIO\Data\ConfigurationManager;
use AIO\Data\DataConst;
use \DateTime;

readonly class AuthManager {
    public const string SESSION_KEY = 'aio_authenticated';
    private const string PENDING_TOKEN_KEY = 'pending_getlogin_token';

    public function __construct(
        private ConfigurationManager $configurationManager,
        private TotpService $totpService,
    ) {
    }

    public function CheckCredentials(string $password) : bool {
        return hash_equals($this->configurationManager->password, $password);
    }

    public function isTwoFactorAuthEnabled() : bool {
        return $this->configurationManager->isTwoFactorAuthEnabled();
    }

    /**
     * Stash a token-based auto-login (getlogin) token in the session so the
     * second-factor step of that flow can validate it once a code is submitted.
     */
    public function storePendingToken(string $token) : void {
        $_SESSION[self::PENDING_TOKEN_KEY] = $token;
    }

    public function hasPendingToken() : bool {
        return $this->getPendingToken() !== '';
    }

    /** Read the pending token without removing it, so a mistimed code can be retried. */
    public function getPendingToken() : string {
        $token = $_SESSION[self::PENDING_TOKEN_KEY] ?? '';
        return is_string($token) ? $token : '';
    }

    /** Remove the pending token from the session (call once the login has succeeded). */
    public function clearPendingToken() : void {
        unset($_SESSION[self::PENDING_TOKEN_KEY]);
    }

    /**
     * Validate the optional TOTP second factor WITHOUT consuming it. Returns
     * [bool $isValid, ?int $matchedCounter]; when valid, the caller must pass
     * $matchedCounter to commitTwoFactorAuth() once the whole login has
     * succeeded, so a code is only burned on a successful login — a mistyped
     * password alongside a correct code does not waste it. Returns [true, null]
     * when 2FA is not set up, so the combined login check is uniform.
     *
     * @return array{0: bool, 1: int|null}
     */
    public function verifyTwoFactorAuthCode(string $code) : array {
        $secret = $this->configurationManager->twoFactorAuthSecret;
        if ($secret === '') {
            return [true, null]; // 2FA not set up → nothing to validate
        }
        return $this->totpService->verify(
            $secret,
            $code,
            $this->configurationManager->twoFactorAuthLastCounter,
        );
    }

    /**
     * Persist the accepted TOTP counter so the same code cannot be reused inside
     * its window (RFC 6238 single-use). Call only after a fully successful login;
     * a null counter (2FA disabled, or nothing matched) is a no-op.
     */
    public function commitTwoFactorAuth(?int $counter) : void {
        if ($counter !== null) {
            // The setter persists immediately (single write) when not batching.
            $this->configurationManager->twoFactorAuthLastCounter = $counter;
        }
    }

    public function CheckToken(string $token) : bool {
        return hash_equals($this->configurationManager->aioToken, $token);
    }

    public function SetAuthState(bool $isLoggedIn) : void {

        if (!$this->IsAuthenticated() && $isLoggedIn === true) {
            session_regenerate_id(true);
            $date = new DateTime();
            $dateTime = $date->getTimestamp();
            $_SESSION['date_time'] = $dateTime;

            $df = disk_free_space(DataConst::GetSessionDirectory());
            if ($df !== false && (int)$df < 10240) {
                error_log(DataConst::GetSessionDirectory() . " has only less than 10KB free space. The login might not succeed because of that!");
            }

            file_put_contents(DataConst::GetSessionDateFile(), (string)$dateTime);
        }

        $_SESSION[self::SESSION_KEY] = $isLoggedIn;
    }

    /**
     * Migrates the authenticated state from an old session (different cookie name) to the new session.
     * Unlike SetAuthState, this method preserves the original login timestamp and does not update
     * the session_date_file, so the session deduplicator is not triggered. This keeps the old session
     * file alive in case the response carrying the new cookie is lost (e.g., due to a 502 error during
     * a mastercontainer update), allowing the client to retry with the old cookie.
     */
    public function MigrateAuthState(int $oldTimestamp) : void {
        $_SESSION[self::SESSION_KEY] = true;
        $_SESSION['date_time'] = $oldTimestamp;
    }

    public function IsAuthenticated() : bool {
        return isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] === true;
    }
}
