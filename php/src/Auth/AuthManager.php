<?php

namespace AIO\Auth;

use AIO\Data\ConfigurationManager;
use AIO\Data\DataConst;
use AIO\Data\InvalidSettingConfigurationException;
use DateTime;

readonly class AuthManager {
    private const string SESSION_KEY = 'aio_authenticated';

    /** @throws InvalidSettingConfigurationException */
    public function CheckCredentials(string $password): bool {
        $config = ConfigurationManager::loadConfigFile();
        return hash_equals($config->GetPassword(), $password);
    }

    /** @throws InvalidSettingConfigurationException */
    public function CheckToken(string $token): bool {
        $config = ConfigurationManager::loadConfigFile();
        return hash_equals($config->GetToken(), $token);
    }

    public function SetAuthState(bool $isLoggedIn): void {

        if (!$this->IsAuthenticated() && $isLoggedIn === true) {
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

    public function IsAuthenticated(): bool {
        return isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] === true;
    }
}
