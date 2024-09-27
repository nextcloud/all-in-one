<?php

namespace AIO\Data;

use AIO\Auth\PasswordGenerator;
use AIO\Controller\DockerController;
use JsonException;
use Random\RandomException;

readonly class ConfigurationManager {
    /* @throws InvalidSettingConfigurationException */
    static function loadConfigFile(): ConfigFile {
        return ConfigFile::parse(file_get_contents(DataConst::GetConfigFile()));
    }

    /**
     * @throws InvalidSettingConfigurationException
     * @throws RandomException
     */
    static function GetAndGenerateSecret(string $secretId): string {
        $config = self::loadConfigFile();
        $secret = $config->getSecret($secretId);
        if ($secret === null) {
            $secret = bin2hex(random_bytes(24));
            $config->setSecret($secretId, $secret);
            self::storeConfigFile($config);
        }

        if ($secretId === 'BORGBACKUP_PASSWORD' && !file_exists(DataConst::GetBackupSecretFile())) {
            self::DoubleSafeBackupSecret($secret);
        }

        return $secret;
    }

    private static function DoubleSafeBackupSecret(string $borgBackupPassword): void {
        file_put_contents(DataConst::GetBackupSecretFile(), $borgBackupPassword);
    }

    public function hasBackupRunOnce(): bool {
        return file_exists(DataConst::GetBackupKeyFile());
    }

    public function GetLastBackupTime(): string {
        if (!file_exists(DataConst::GetBackupArchivesList())) {
            return '';
        }

        $content = file_get_contents(DataConst::GetBackupArchivesList());
        if ($content === '' || $content === false) {
            return '';
        }

        $lastBackupLines = explode("\n", $content);
        $lastBackupLine = "";
        if (count($lastBackupLines) >= 2) {
            $lastBackupLine = $lastBackupLines[sizeof($lastBackupLines) - 2];
        }
        if ($lastBackupLine === "") {
            return '';
        }

        $lastBackupTimes = explode(",", $lastBackupLine);
        $lastBackupTime = $lastBackupTimes[1];
        if ($lastBackupTime === "") {
            return '';
        }

        return $lastBackupTime;
    }

    public function GetBackupTimes(): array {
        if (!file_exists(DataConst::GetBackupArchivesList())) {
            return [];
        }

        $content = file_get_contents(DataConst::GetBackupArchivesList());
        if ($content === '') {
            return [];
        }

        $backupLines = explode("\n", $content);
        $backupTimes = [];
        foreach ($backupLines as $lines) {
            if ($lines !== "") {
                $backupTimesTemp = explode(',', $lines);
                $backupTimes[] = $backupTimesTemp[1];
            }
        }

        // Reverse the array to list newest backup first
        return array_reverse($backupTimes);
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public static function storeConfigFile(ConfigFile $config): void {
        if (!is_dir(DataConst::GetDataDirectory())) {
            throw new InvalidSettingConfigurationException(DataConst::GetDataDirectory() . " does not exist! Something was set up falsely!");
        }
        $df = disk_free_space(DataConst::GetDataDirectory());
        try {
            $content = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidSettingConfigurationException('Failed to encode JSON data', previous: $e);
        }
        $size = strlen($content) + 10240;
        if ($df !== false && (int)$df < $size) {
            throw new InvalidSettingConfigurationException(DataConst::GetDataDirectory() . " does not have enough space for writing the config file! Not writing it back!");
        }
        file_put_contents(DataConst::GetConfigFile(), $content);
    }


    /**
     * @throws InvalidSettingConfigurationException
     */
    public static function SetDailyBackupTime(string $time, bool $enableAutomaticUpdates, bool $successNotification): void {
        if ($time === "") {
            throw new InvalidSettingConfigurationException("The daily backup time must not be empty!");
        }

        if (!preg_match("#^[0-1][0-9]:[0-5][0-9]$#", $time) && !preg_match("#^2[0-3]:[0-5][0-9]$#", $time)) {
            throw new InvalidSettingConfigurationException("You did not enter a correct time! One correct example is '04:00'!");
        }

        if ($enableAutomaticUpdates === false) {
            $time .= PHP_EOL . 'automaticUpdatesAreNotEnabled';
        } else {
            $time .= PHP_EOL;
        }
        if ($successNotification === false) {
            $time .= PHP_EOL . 'successNotificationsAreNotEnabled';
        } else {
            $time .= PHP_EOL;
        }
        file_put_contents(DataConst::GetDailyBackupTimeFile(), $time);
    }

    public function GetDailyBackupTime(): string {
        if (!file_exists(DataConst::GetDailyBackupTimeFile())) {
            return '';
        }
        $dailyBackupFile = file_get_contents(DataConst::GetDailyBackupTimeFile());
        $dailyBackupFileArray = explode("\n", $dailyBackupFile);
        return $dailyBackupFileArray[0];
    }

    static function areAutomaticUpdatesEnabled(): bool {
        if (!file_exists(DataConst::GetDailyBackupTimeFile())) {
            return false;
        }
        $dailyBackupFile = file_get_contents(DataConst::GetDailyBackupTimeFile());
        $dailyBackupFileArray = explode("\n", $dailyBackupFile);
        if (isset($dailyBackupFileArray[1]) && $dailyBackupFileArray[1] === 'automaticUpdatesAreNotEnabled') {
            return false;
        } else {
            return true;
        }
    }

    public function DeleteDailyBackupTime(): void {
        if (file_exists(DataConst::GetDailyBackupTimeFile())) {
            unlink(DataConst::GetDailyBackupTimeFile());
        }
    }

    /** @throws InvalidSettingConfigurationException */
    public static function SetAdditionalBackupDirectories(string $additionalBackupDirectories): void {
        $additionalBackupDirectoriesArray = explode("\n", $additionalBackupDirectories);
        $validDirectories = '';
        foreach ($additionalBackupDirectoriesArray as $entry) {
            // Trim all unwanted chars on both sites
            $entry = trim($entry);
            if ($entry !== "") {
                if (!preg_match("#^/[.0-9a-zA-Z/_-]+$#", $entry) && !preg_match("#^[.0-9a-zA-Z_-]+$#", $entry)) {
                    throw new InvalidSettingConfigurationException("You entered unallowed characters! Problematic is " . $entry);
                }
                $validDirectories .= rtrim($entry, '/') . PHP_EOL;
            }
        }

        if ($validDirectories === '') {
            unlink(DataConst::GetAdditionalBackupDirectoriesFile());
        } else {
            file_put_contents(DataConst::GetAdditionalBackupDirectoriesFile(), $validDirectories);
        }
    }

    static function GetAdditionalBackupDirectoriesString(): string {
        return file_exists(DataConst::GetAdditionalBackupDirectoriesFile())
            ? file_get_contents(DataConst::GetAdditionalBackupDirectoriesFile())
            : '';
    }

    static function GetAdditionalBackupDirectoriesArray(): array {
        $additionalBackupDirectories = self::GetAdditionalBackupDirectoriesString();
        $additionalBackupDirectoriesArray = explode("\n", $additionalBackupDirectories);
        $additionalBackupDirectoriesArray = array_unique($additionalBackupDirectoriesArray, SORT_REGULAR);
        return $additionalBackupDirectoriesArray;
    }

    static function isDailyBackupRunning(): bool {
        if (file_exists(DataConst::GetDailyBackupBlockFile())) {
            return true;
        }
        return false;
    }

    public function shouldDomainValidationBeSkipped(): bool {
        if (getenv('SKIP_DOMAIN_VALIDATION') !== false) {
            return true;
        }
        return false;
    }

    static function GetNextcloudStartupApps(): string {
        $apps = getenv('NEXTCLOUD_STARTUP_APPS');
        if (is_string($apps)) {
            return trim($apps);
        }
        return 'deck twofactor_totp tasks calendar contacts notes';
    }
}
