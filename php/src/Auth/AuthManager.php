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
    private const int SIGNATURE_MAX_AGE_SECONDS = 60;

    public function __construct(
        private ConfigurationManager $configurationManager
    ) {
    }

    public function CheckCredentials(string $password) : bool {
        return hash_equals($this->configurationManager->password, $password);
    }

    public function CheckToken(string $token) : bool {
        $aioToken = $this->configurationManager->aioToken;
        // Do not allow login if the token hasn't been set yet.
        return $aioToken !== '' && hash_equals($aioToken, $token);
    }

    /** Login via a timestamp signed with the private key that was handed to the Nextcloud container. */
    public function checkSignature(string $signature) : bool {
        $timestamp = self::openSignedTimestamp($signature, $this->configurationManager->aioUnblockLoginPublicKey);
        if ($timestamp === null) {
            return false;
        }

        $timeElapsed = time() - $timestamp;
        if ($timeElapsed > self::SIGNATURE_MAX_AGE_SECONDS || $timeElapsed < 0) {
            return false;
        }

        // Prevent replay: reject signatures that have already been used
        return apcu_add('used_signature_' . hash('sha256', $signature), true, self::SIGNATURE_MAX_AGE_SECONDS);
    }

    /** @return array{string, string} [privateKeyBase64, publicKeyBase64] */
    public static function generateKeyPair() : array {
        $keypair = sodium_crypto_sign_keypair();
        return [
            sodium_bin2base64(sodium_crypto_sign_secretkey($keypair), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
            sodium_bin2base64(sodium_crypto_sign_publickey($keypair), SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING),
        ];
    }

    /** Returns the signed timestamp, or null if the signature is malformed or invalid. */
    public static function openSignedTimestamp(string $signature, string $publicKeyBase64) : ?int {
        if ($publicKeyBase64 === '' || $signature === '') {
            return null;
        }

        try {
            $publicKeyBin = sodium_base642bin($publicKeyBase64, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $signatureBin = sodium_base642bin($signature, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
            $timestamp = sodium_crypto_sign_open($signatureBin, $publicKeyBin);
        } catch (\SodiumException) {
            return null;
        }

        if ($timestamp === false || !ctype_digit($timestamp)) {
            return null;
        }
        return (int) $timestamp;
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
