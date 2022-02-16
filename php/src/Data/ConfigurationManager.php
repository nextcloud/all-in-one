<?php

namespace AIO\Data;

use AIO\Auth\PasswordGenerator;
use AIO\Controller\DockerController;

class ConfigurationManager
{
    public function GetConfig() : array
    {
        if(file_exists(DataConst::GetConfigFile()))
        {
            $configContent = file_get_contents(DataConst::GetConfigFile());
            return json_decode($configContent, true);
        }

        return [];
    }

    public function GetPassword() : string {
        return $this->GetConfig()['password'];
    }

    public function GetToken() : string {
        return $this->GetConfig()['AIO_TOKEN'];
    }

    public function SetPassword(string $password) : void {
        $config = $this->GetConfig();
        $config['password'] = $password;
        $this->WriteConfig($config);
    }

    public function GetSecret(string $secretId) : string {
        $config = $this->GetConfig();
        if(!isset($config['secrets'][$secretId])) {
            $config['secrets'][$secretId] = bin2hex(random_bytes(24));
            $this->WriteConfig($config);
        }

        if ($secretId === 'BORGBACKUP_PASSWORD' && !file_exists(DataConst::GetBackupSecretFile())) {
            $this->DoubleSafeBackupSecret($config['secrets'][$secretId]);
        }

        return $config['secrets'][$secretId];
    }

    private function DoubleSafeBackupSecret(string $borgBackupPassword) {
        file_put_contents(DataConst::GetBackupSecretFile(), $borgBackupPassword);
    }

    public function hasBackupRunOnce() : bool {
        if (!file_exists(DataConst::GetBackupKeyFile())) {
            return false;
        } else {
            return true;
        }
    }

    public function GetLastBackupTime() : string {
        if (!file_exists(DataConst::GetBackupArchivesList())) {
            return '';
        }
        
        $content = file_get_contents(DataConst::GetBackupArchivesList());
        if ($content === '') {
            return '';
        }

        $lastBackupLines = explode("\n", $content);
        $lastBackupLine = $lastBackupLines[sizeof($lastBackupLines) - 2];
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

    public function GetBackupTimes() : array {
        if (!file_exists(DataConst::GetBackupArchivesList())) {
            return [];
        }
        
        $content = file_get_contents(DataConst::GetBackupArchivesList());
        if ($content === '') {
            return [];
        }

        $backupLines = explode("\n", $content);
        $backupTimes = [];
        foreach($backupLines as $lines) {
            if ($lines !== "") {
                $backupTimesTemp = explode(',', $lines);
                $backupTimes[] = $backupTimesTemp[1];     
            }
        }
        
        if (!is_array($backupTimes)) {
            return [];
        }

        return $backupTimes;
    }

    public function wasStartButtonClicked() : bool {
        if (isset($this->GetConfig()['wasStartButtonClicked'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetDomain(string $domain) : void {
        // Validate URL
        if (!filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
            throw new InvalidSettingConfigurationException("Domain is not in a valid format!");
        }

        $dnsRecordIP = gethostbyname($domain);

        // Validate IP
        if(!filter_var($dnsRecordIP, FILTER_VALIDATE_IP)) {
            throw new InvalidSettingConfigurationException("DNS config is not set or domain is not in a valid format!");
        }

        $connection = @fsockopen($domain, 443, $errno, $errstr, 0.1);
        if ($connection) {
            fclose($connection);
        } else {
            throw new InvalidSettingConfigurationException("The server is not reachable on Port 443.");
        }

        // Get Instance ID
        $instanceID = $this->GetSecret('INSTANCE_ID');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,'http://' . $domain . ':443');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        # Get rid of trailing \n
        $response = str_replace("\n", "", $response);

        if($response !== $instanceID) {
            throw new InvalidSettingConfigurationException("Domain does not point to this server.");
        }

        // Write domain
        $config = $this->GetConfig();
        $config['domain'] = $domain;
        $this->WriteConfig($config);
    }

    public function GetDomain() : string {
        $config = $this->GetConfig();
        if(!isset($config['domain'])) {
            $config['domain'] = '';
        }

        return $config['domain'];
    }

    public function GetBackupMode() : string {
        $config = $this->GetConfig();
        if(!isset($config['backup-mode'])) {
            $config['backup-mode'] = '';
        }

        return $config['backup-mode'];
    }

    public function GetSelectedRestoreTime() : string {
        $config = $this->GetConfig();
        if(!isset($config['selected-restore-time'])) {
            $config['selected-restore-time'] = '';
        }

        return $config['selected-restore-time'];
    }

    public function GetAIOURL() : string {
        $config = $this->GetConfig();
        if(!isset($config['AIO_URL'])) {
            $config['AIO_URL'] = '';
        }

        return $config['AIO_URL'];
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetBorgBackupHostLocation(string $location) : void {
        $allowedPrefixes = [
            '/mnt/',
            '/media/',
        ];

        $isValidPath = false;
        foreach($allowedPrefixes as $allowedPrefix) {
            if(str_starts_with($location, $allowedPrefix) && !str_ends_with($location, '/')) {
                $isValidPath = true;
                break;
            }
            if ($location === '/var/backups') {
                $isValidPath = true;
                break;
            }
        }

        if(!$isValidPath) {
            throw new InvalidSettingConfigurationException("The path must start with '/mnt/' or '/media/' or be equal to '/var/backups'.");
        }


        $config = $this->GetConfig();
        $config['borg_backup_host_location'] = $location;
        $this->WriteConfig($config);
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function WriteConfig(array $config) : void {
        if(!is_dir(DataConst::GetDataDirectory())) {
            throw new InvalidSettingConfigurationException(DataConst::GetDataDirectory() . " does not exist! Something was set up falsely!");
        }
        file_put_contents(DataConst::GetConfigFile(), json_encode($config));
    }

    public function GetBorgBackupHostLocation() : string {
        $config = $this->GetConfig();
        if(!isset($config['borg_backup_host_location'])) {
            $config['borg_backup_host_location'] = '';
        }

        return $config['borg_backup_host_location'];
    }

    public function GetBorgBackupMode() : string {
        $config = $this->GetConfig();
        if(!isset($config['backup-mode'])) {
            $config['backup-mode'] = '';
        }

        return $config['backup-mode'];
    }
}
