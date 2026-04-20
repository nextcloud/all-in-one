<?php
declare(strict_types=1);

namespace AIO\Auth;

use AIO\Data\ConfigurationManager;
use AIO\Data\DataConst;
use \DateTime;

readonly class AuthManager {
    public const string SESSION_KEY = 'aio_authenticated';

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
        $tokenHash = hash('sha256', $token);
        $cacheKey = 'used_token_' . $tokenHash;
        if (apcu_fetch($cacheKey) !== false) {
            return false;
        }
        apcu_add($cacheKey, true, 60);

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
    }

    public function IsAuthenticated() : bool {
        return isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] === true;
    }
}
