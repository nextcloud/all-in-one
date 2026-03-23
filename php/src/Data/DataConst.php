<?php
declare(strict_types=1);

namespace AIO\Data;

class DataConst {
    public static function GetDataDirectory() : string {
        if(is_dir('/mnt/docker-aio-config/data/')) {
            return '/mnt/docker-aio-config/data/';
        }

        return (string)realpath(__DIR__ . '/../../data/');
    }

    public static function GetSessionDirectory() : string {
        if(is_dir('/mnt/docker-aio-config/session/')) {
            return '/mnt/docker-aio-config/session/';
        }

        return (string)realpath(__DIR__ . '/../../session/');
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
        return (string)realpath(__DIR__ . '/../../../community-containers/');
    }

    public static function GetCollaboraSeccompProfilePath() : string {
        return (string)realpath(__DIR__ . '/../../cool-seccomp-profile.json');
    }

    public static function GetContainersDefinitionPath() : string {
        return (string)realpath(__DIR__ . '/../../containers.json');
    }

    public static function GetAioVersionFile() : string {
        return (string)realpath(__DIR__ . '/../../templates/includes/aio-version.twig');
    }
}
