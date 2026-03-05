<?php
declare(strict_types=1);

namespace AIO\Auth;

use AIO\Data\ConfigurationManager;
use AIO\Data\DataConst;
use \DateTime;

readonly class AuthManager {
    private const string SESSION_KEY = 'aio_authenticated';
    private const int LOGIN_UNBLOCK_SECONDS = 300;
    private const int LOGIN_UNBLOCK_MAX_FAILED_ATTEMPTS = 5;

    public function __construct(
        private ConfigurationManager $configurationManager
    ) {
    }

    public function CheckCredentials(string $password) : bool {
        return hash_equals($this->configurationManager->password, $password);
    }

    public function CheckToken(string $token) : bool {
        $publicKeyBase64 = $this->configurationManager->aioPublicKey;
        if ($publicKeyBase64 === '' || $token === '') {
            return false;
        }

        try {
            $publicKeyBin = sodium_base642bin($publicKeyBase64, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $tokenBin = sodium_base642bin($token, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (\SodiumException) {
            return false;
        }

        $timestamp = sodium_crypto_sign_open($tokenBin, $publicKeyBin);

        if ($timestamp === false) {
            return false;
        }

        $timeElapsed = time() - (int) $timestamp;
        if ($timeElapsed > 60 || $timeElapsed < 0) {
            return false;
        }

        // Prevent token replay: reject tokens that have already been used
        if (!apcu_add('used_token_' . hash('sha256', $token), true, 60)) return false;

        return true;
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
        if ($isLoggedIn) {
            unset($_SESSION['aio_login_unblocked_until'], $_SESSION['aio_login_failed_attempts']);
        }
    }

    // Allows the login form to be used in this session for a few minutes, even if it's otherwise blocked.
    public function UnblockLoginForSession() : void {
        $_SESSION['aio_login_unblocked_until'] = time() + self::LOGIN_UNBLOCK_SECONDS;
        $_SESSION['aio_login_failed_attempts'] = 0;
    }

    public function GetLoginUnblockedNotice() : ?string {
        if (!$this->IsLoginUnblockedForSession()) {
            return null;
        }
        return "This login form is now available to you for up to " . (self::LOGIN_UNBLOCK_SECONDS / 60) . " minutes and max. " . self::LOGIN_UNBLOCK_MAX_FAILED_ATTEMPTS . " attempts.";
    }

    public function IsLoginUnblockedForSession() : bool {
        return time() < ($_SESSION['aio_login_unblocked_until'] ?? 0)
            && ($_SESSION['aio_login_failed_attempts'] ?? 0) < self::LOGIN_UNBLOCK_MAX_FAILED_ATTEMPTS;
    }

    public function RegisterFailedLoginAttempt() : void {
        $_SESSION['aio_login_failed_attempts'] = ($_SESSION['aio_login_failed_attempts'] ?? 0) + 1;
    }

    public function IsAuthenticated() : bool {
        return isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] === true;
    }
}
