<?php

namespace AIO\Data;

use AIO\Auth\PasswordGenerator;
use Random\RandomException;

readonly class Setup {
    /**
     * @throws InvalidSettingConfigurationException
     * @throws RandomException
     */
    static function Setup(): string {
        if (!self::CanBeInstalled()) {
            return '';
        }

        $password = PasswordGenerator::GeneratePassword(8);
        ConfigurationManager::storeConfigFile(ConfigFile::blank($password));
        return $password;
    }

    static function CanBeInstalled(): bool {
        return !file_exists(DataConst::GetConfigFile());
    }
}
