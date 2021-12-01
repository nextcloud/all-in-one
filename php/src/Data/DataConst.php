<?php

namespace AIO\Data;

class DataConst {
    public static function GetDataDirectory() : string {
        if(is_dir('/mnt/docker-aio-config/data/')) {
            return '/mnt/docker-aio-config/data/';
        }

        return realpath(__DIR__ . '/../../data/');
    }

    public static function GetSessionDirectory() : string {
        if(is_dir('/mnt/docker-aio-config/session/')) {
            return '/mnt/docker-aio-config/session/';
        }

        return realpath(__DIR__ . '/../../session/');
    }

    public static function GetConfigFile() : string {
        return self::GetDataDirectory() . '/configuration.json';
    }

    public static function GetTempSetupFile() : string {
        return self::GetDataDirectory() . '/temp.setup';
    }

    public static function GetBackupSecretFile() : string {
        return self::GetDataDirectory() . '/backupsecret';
    }

    public static function GetBackupKeyFile() : string {
        return self::GetDataDirectory() . '/borg.config';
    }

    public static function GetBackupArchivesList() : string {
        return self::GetDataDirectory() . '/backup_archives.list';
    }
}
