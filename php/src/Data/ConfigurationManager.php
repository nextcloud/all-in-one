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

    private function DoubleSafeBackupSecret(string $borgBackupPassword) : void {
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

        // Reverse the array to list newest backup first
        $backupTimes = array_reverse($backupTimes);

        return $backupTimes;
    }

    public function wasStartButtonClicked() : bool {
        if (isset($this->GetConfig()['wasStartButtonClicked'])) {
            return true;
        } else {
            return false;
        }
    }

    public function isx64Platform() : bool {
        if (php_uname('m') === 'x86_64') {
            return true;
        } else {
            return false;
        }
    }

    public function isClamavEnabled() : bool {
        $config = $this->GetConfig();
        if (isset($config['isClamavEnabled']) && $config['isClamavEnabled'] === 1) {
            return true;
        } else {
            return false;
        }
    }

    public function SetClamavEnabledState(int $value) : void {
        $config = $this->GetConfig();
        $config['isClamavEnabled'] = $value;
        $this->WriteConfig($config);
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetDomain(string $domain) : void {
        // Validate domain
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidSettingConfigurationException("Domain is not in a valid format!");
        }

        // Validate that it is not an IP-address
        if(filter_var($domain, FILTER_VALIDATE_IP)) {
            throw new InvalidSettingConfigurationException("Please enter a domain and not an IP-address!");
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

        // set protocol
        $port = $this->GetApachePort();
        if ($port !== '443') {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $protocol . $domain . ':443');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = (string)curl_exec($ch);
        # Get rid of trailing \n
        $response = str_replace("\n", "", $response);

        if($response !== $instanceID) {
            throw new InvalidSettingConfigurationException("Domain does not point to this server or reverse proxy not configured correctly.");
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
    public function ChangeMasterPassword(string $currentPassword, string $newPassword) : void {
        if ($currentPassword === '') {
            throw new InvalidSettingConfigurationException("Please enter your current password.");
        }

        if ($currentPassword !== $this->GetPassword()) {
            throw new InvalidSettingConfigurationException("The entered current password is not correct.");
        }

        if ($newPassword === '') {
            throw new InvalidSettingConfigurationException("Please enter a new password.");
        }

        if (strlen($newPassword) < 24) {
            throw new InvalidSettingConfigurationException("New passwords must be >= 24 digits.");
        }

        if (!preg_match("#^[a-zA-Z0-9 ]+$#", $newPassword)) {
            throw new InvalidSettingConfigurationException('Not allowed characters in the new password.');
        }

        // All checks pass so set the password
        $this->SetPassword($newPassword);
    }

    public function GetApachePort() : string {
        $envVariableName = 'APACHE_PORT';
        $configName = 'apache_port';
        $defaultValue = '443';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
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

    private function GetEnvironmentalVariableOrConfig(string $envVariableName, string $configName, string $defaultValue) : string {
        $envVariableOutput = getenv($envVariableName);
        if ($envVariableOutput === false) {
            $config = $this->GetConfig();
            if (!isset($config[$configName]) || $config[$configName] === '') {
                $config[$configName] = $defaultValue;
            }
            return $config[$configName];
        }
        if(file_exists(DataConst::GetConfigFile())) {
            $config = $this->GetConfig();
            if (!isset($config[$configName])) {
                $config[$configName] = '';
            }
            if ($envVariableOutput !== $config[$configName]) {
                $config[$configName] = $envVariableOutput;
                $this->WriteConfig($config);
            }
        }
        return $envVariableOutput;
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

    public function GetNextcloudMount() : string {
        $envVariableName = 'NEXTCLOUD_MOUNT';
        $configName = 'nextcloud_mount';
        $defaultValue = '';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function GetNextcloudDatadirMount() : string {
        $envVariableName = 'NEXTCLOUD_DATADIR';
        $configName = 'nextcloud_datadir';
        $defaultValue = 'nextcloud_aio_nextcloud_data';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }
}
