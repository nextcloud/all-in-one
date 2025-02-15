<?php

namespace AIO\Data;

class DataConst {
    public static function GetDataDirectory() : string {
        $dataDirectory = '/mnt/docker-aio-config/data/';

        if (is_dir($dataDirectory)) {
            return $dataDirectory;
        }

        $newDataDirectory = realpath(__DIR__ . '/../../data/');

        if ($newDataDirectory === false) {
            return $dataDirectory;
        }

        return $newDataDirectory;
    }

    public static function GetSessionDirectory() : string {
        $sessionDirectory = '/mnt/docker-aio-config/session/';

        if (is_dir($sessionDirectory)) {
            return $sessionDirectory;
        }

        $newSessionDirectory = realpath(__DIR__ . '/../../session/');

        if ($newSessionDirectory === false) {
            return $sessionDirectory;
        }

        return $newSessionDirectory;
    }

    public static function GetConfigFile() : string {
        return self::GetDataDirectory() . '/configuration.json';
    }

    public static function GetBackupPublicKey() : string {
        return self::GetDataDirectory() . '/id_borg.pub';
    }

    public static function GetBackupSecretFile() : string {
        return self::GetDataDirectory() . '/backupsecret';
    }

    public static function GetDailyBackupTimeFile() : string {
        return self::GetDataDirectory() . '/daily_backup_time';
    }

    public static function GetAdditionalBackupDirectoriesFile() : string {
        return self::GetDataDirectory() . '/additional_backup_directories';
    }

    public static function GetDailyBackupBlockFile() : string {
        return self::GetDataDirectory() . '/daily_backup_running';
    }

    public static function GetBackupKeyFile() : string {
        return self::GetDataDirectory() . '/borg.config';
    }

    public static function GetBackupArchivesList() : string {
        return self::GetDataDirectory() . '/backup_archives.list';
    }

    public static function GetSessionDateFile() : string {
        return self::GetDataDirectory() . '/session_date_file';
    }

    public static function GetCommunityContainersDirectory() : string {
        $communityContainersDirectory = '/var/www/docker-aio/community-containers/';

        if (is_dir($communityContainersDirectory)) {
            return $communityContainersDirectory;
        }

        $newCommunityContainersDirectory = realpath(__DIR__ . '/../../../community-containers/');

        if ($newCommunityContainersDirectory === false) {
            return $communityContainersDirectory;
        }

        return $newCommunityContainersDirectory;
    }
}
