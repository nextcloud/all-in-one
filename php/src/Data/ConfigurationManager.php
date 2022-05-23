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

    public function isOnlyofficeEnabled() : bool {
        $config = $this->GetConfig();
        if (isset($config['isOnlyofficeEnabled']) && $config['isOnlyofficeEnabled'] === 1) {
            return true;
        } else {
            return false;
        }
    }

    public function SetOnlyofficeEnabledState(int $value) : void {
        $config = $this->GetConfig();
        $config['isOnlyofficeEnabled'] = $value;
        $this->WriteConfig($config);
    }

    public function isCollaboraEnabled() : bool {
        $config = $this->GetConfig();
        if (isset($config['isCollaboraEnabled']) && $config['isCollaboraEnabled'] === 0) {
            return false;
        } else {
            return true;
        }
    }

    public function SetCollaboraEnabledState(int $value) : void {
        $config = $this->GetConfig();
        $config['isCollaboraEnabled'] = $value;
        $this->WriteConfig($config);
    }

    public function isTalkEnabled() : bool {
        $config = $this->GetConfig();
        if (isset($config['isTalkEnabled']) && $config['isTalkEnabled'] === 0) {
            return false;
        } else {
            return true;
        }
    }

    public function SetTalkEnabledState(int $value) : void {
        $config = $this->GetConfig();
        $config['isTalkEnabled'] = $value;
        $this->WriteConfig($config);
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetDomain(string $domain) : void {
        // Validate domain
        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new InvalidSettingConfigurationException("Domain is not a valid domain!");
        }

        // Validate that it is not an IP-address
        if(filter_var($domain, FILTER_VALIDATE_IP)) {
            throw new InvalidSettingConfigurationException("Please enter a domain and not an IP-address!");
        }

        $dnsRecordIP = gethostbyname($domain);

        // Validate IP
        if(!filter_var($dnsRecordIP, FILTER_VALIDATE_IP)) {
            throw new InvalidSettingConfigurationException("DNS config is not set for this domain or the domain is not a valid domain! (It was found to be set to '" . $dnsRecordIP . "')");
        }

        // Check if port 443 is open
        $connection = @fsockopen($domain, 443, $errno, $errstr, 10);
        if ($connection) {
            fclose($connection);
        } else {
            throw new InvalidSettingConfigurationException("The server is not reachable on Port 443. You can verify this e.g. with 'https://portchecker.co/' by entering your domain there as ip-address and port 443 as port.");
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

        // Check if response is correct
        $ch = curl_init();
        $testUrl = $protocol . $domain . ':443';
        curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = (string)curl_exec($ch);
        # Get rid of trailing \n
        $response = str_replace("\n", "", $response);

        if ($response !== $instanceID) {
            error_log('The response of the connection attempt to "' . $testUrl . '" was: ' . $response);
            throw new InvalidSettingConfigurationException("Domain does not point to this server or the reverse proxy is not configured correctly. See the mastercontainer logs for more details. ('sudo docker logs -f nextcloud-aio-mastercontainer')");
        }

        // Write domain
        $config = $this->GetConfig();
        $config['domain'] = $domain;
        // Reset the borg restore password when setting the domain
        $config['borg_restore_password'] = '';
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
        $isValidPath = false;
        if (str_starts_with($location, '/') && !str_ends_with($location, '/')) {
            $isValidPath = true;
        } elseif ($location === 'nextcloud_aio_backupdir') {
            $isValidPath = true;
        }

        if (!$isValidPath) {
            throw new InvalidSettingConfigurationException("The path must start with '/', and must not end with '/'!");
        }


        $config = $this->GetConfig();
        $config['borg_backup_host_location'] = $location;
        $this->WriteConfig($config);
    }

        /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetBorgRestoreHostLocationAndPassword(string $location, string $password) : void {
        if ($location === '') {
            throw new InvalidSettingConfigurationException("Please enter a path!");
        }
        
        $isValidPath = false;
        if (str_starts_with($location, '/') && !str_ends_with($location, '/')) {
            $isValidPath = true;
        } elseif ($location === 'nextcloud_aio_backupdir') {
            $isValidPath = true;
        }

        if (!$isValidPath) {
            throw new InvalidSettingConfigurationException("The path must start with '/', and must not end with '/'!");
        }

        if ($password === '') {
            throw new InvalidSettingConfigurationException("Please enter the password!");
        }

        $config = $this->GetConfig();
        $config['borg_backup_host_location'] = $location;
        $config['borg_restore_password'] = $password;
        $config['instance_restore_attempt'] = 1;
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

    public function GetBorgRestorePassword() : string {
        $config = $this->GetConfig();
        if(!isset($config['borg_restore_password'])) {
            $config['borg_restore_password'] = '';
        }

        return $config['borg_restore_password'];
    }

    public function isInstanceRestoreAttempt() : bool {
        $config = $this->GetConfig();
        if(!isset($config['instance_restore_attempt'])) {
            $config['instance_restore_attempt'] = '';
        }

        if ($config['instance_restore_attempt'] === 1) {
            return true;
        }
        return false;
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

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetDailyBackupTime(string $time) : void {
        if ($time === "") {
            throw new InvalidSettingConfigurationException("The daily backup time must not be empty!");
        }

        if (!preg_match("#^[0-1][0-9]:[0-5][0-9]$#", $time) && !preg_match("#^2[0-3]:[0-5][0-9]$#", $time)) {
            throw new InvalidSettingConfigurationException("You did not enter a correct time! One correct example is '04:00'!");
        }
        
        file_put_contents(DataConst::GetDailyBackupTimeFile(), $time);
    }

    public function GetDailyBackupTime() : string {
        if (!file_exists(DataConst::GetDailyBackupTimeFile())) {
            return '';
        }
        return file_get_contents(DataConst::GetDailyBackupTimeFile());
    }

    public function DeleteDailyBackupTime() : void {
        if (file_exists(DataConst::GetDailyBackupTimeFile())) {
            unlink(DataConst::GetDailyBackupTimeFile());
        }
    }

    public function isDailyBackupRunning() : bool {
        if (file_exists(DataConst::GetDailyBackupBlockFile())) {
            return true;
        }
        return false;
    }

    public function GetTimezone() : string {
        $config = $this->GetConfig();
        if(!isset($config['timezone'])) {
            $config['timezone'] = '';
        }

        return $config['timezone'];
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetTimezone(string $timezone) : void {
        if ($timezone === "") {
            throw new InvalidSettingConfigurationException("The timezone must not be empty!");
        }

        if (!preg_match("#^[a-zA-Z0-9_\-\/\+]+$#", $timezone)) {
            throw new InvalidSettingConfigurationException("The entered timezone does not seem to be a valid timezone!");
        }

        $config = $this->GetConfig();
        $config['timezone'] = $timezone;
        $this->WriteConfig($config);
    }

    public function DeleteTimezone() : void {
        $config = $this->GetConfig();
        $config['timezone'] = '';
        $this->WriteConfig($config);
    }
}
