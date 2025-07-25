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
            return json_decode($configContent, true, 512, JSON_THROW_ON_ERROR);
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

    public function GetAndGenerateSecret(string $secretId) : string {
        if ($secretId === '') {
            return '';
        }

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

    public function GetSecret(string $secretId) : string {
        $config = $this->GetConfig();
        if(!isset($config['secrets'][$secretId])) {
            $config['secrets'][$secretId] = "";
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

    private function isx64Platform() : bool {
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

    public function isDockerSocketProxyEnabled() : bool {
        $config = $this->GetConfig();
        if (isset($config['isDockerSocketProxyEnabled']) && $config['isDockerSocketProxyEnabled'] === 1) {
            return true;
        } else {
            return false;
        }
    }

    public function SetDockerSocketProxyEnabledState(int $value) : void {
        $config = $this->GetConfig();
        $config['isDockerSocketProxyEnabled'] = $value;
        $this->WriteConfig($config);
    }

    public function isWhiteboardEnabled() : bool {
        $config = $this->GetConfig();
        if (isset($config['isWhiteboardEnabled']) && $config['isWhiteboardEnabled'] === 0) {
            return false;
        } else {
            return true;
        }
    }

    public function SetWhiteboardEnabledState(int $value) : void {
        $config = $this->GetConfig();
        $config['isWhiteboardEnabled'] = $value;
        $this->WriteConfig($config);
    }

    public function SetClamavEnabledState(int $value) : void {
        $config = $this->GetConfig();
        $config['isClamavEnabled'] = $value;
        $this->WriteConfig($config);
    }

    public function isImaginaryEnabled() : bool {
        $config = $this->GetConfig();
        if (isset($config['isImaginaryEnabled']) && $config['isImaginaryEnabled'] === 0) {
            return false;
        } else {
            return true;
        }
    }

    public function SetImaginaryEnabledState(int $value) : void {
        $config = $this->GetConfig();
        $config['isImaginaryEnabled'] = $value;
        $this->WriteConfig($config);
    }

    public function isFulltextsearchEnabled() : bool {
        $config = $this->GetConfig();
        if (isset($config['isFulltextsearchEnabled']) && $config['isFulltextsearchEnabled'] === 1) {
            return true;
        } else {
            return false;
        }
    }

    public function SetFulltextsearchEnabledState(int $value) : void {
        // Elasticsearch does not work on kernels without seccomp anymore. See https://github.com/nextcloud/all-in-one/discussions/5768
        if ($this->GetCollaboraSeccompDisabledState() === 'true') {
            $value = 0;
        }

        $config = $this->GetConfig();
        $config['isFulltextsearchEnabled'] = $value;
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

    public function isTalkRecordingEnabled() : bool {
        if (!$this->isTalkEnabled()) {
            return false;
        }
        $config = $this->GetConfig();
        if (isset($config['isTalkRecordingEnabled']) && $config['isTalkRecordingEnabled'] === 1) {
            return true;
        } else {
            return false;
        }
    }

    public function SetTalkRecordingEnabledState(int $value) : void {
        if (!$this->isTalkEnabled()) {
            $value = 0;
        }

        // Currently only works on x64. See https://github.com/nextcloud/nextcloud-talk-recording/issues/17
        if (!$this->isx64Platform()) {
            $value = 0;
        }

        $config = $this->GetConfig();
        $config['isTalkRecordingEnabled'] = $value;
        $this->WriteConfig($config);
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetDomain(string $domain) : void {
        // Validate that at least one dot is contained
        if (!str_contains($domain, '.')) {
            throw new InvalidSettingConfigurationException("Domain must contain at least one dot!");
        }

        // Validate that no slashes are contained
        if (str_contains($domain, '/')) {
            throw new InvalidSettingConfigurationException("Domain must not contain slashes!");
        }

        // Validate that no colons are contained
        if (str_contains($domain, ':')) {
            throw new InvalidSettingConfigurationException("Domain must not contain colons!");
        }

        // Validate domain
        if (filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false) {
            throw new InvalidSettingConfigurationException("Domain is not a valid domain!");
        }

        // Validate that it is not an IP-address
        if(filter_var($domain, FILTER_VALIDATE_IP)) {
            throw new InvalidSettingConfigurationException("Please enter a domain and not an IP-address!");
        }

        // Skip domain validation if opted in to do so
        if (!$this->shouldDomainValidationBeSkipped()) {

            $dnsRecordIP = gethostbyname($domain);
            if ($dnsRecordIP === $domain) {
                $dnsRecordIP = '';
            }

            if (empty($dnsRecordIP)) {
                $record = dns_get_record($domain, DNS_AAAA);
                if (isset($record[0]['ipv6']) && !empty($record[0]['ipv6'])) {
                    $dnsRecordIP = $record[0]['ipv6'];
                }
            }

            // Validate IP
            if (!filter_var($dnsRecordIP, FILTER_VALIDATE_IP)) {
                throw new InvalidSettingConfigurationException("DNS config is not set for this domain or the domain is not a valid domain! (It was found to be set to '" . $dnsRecordIP . "')");
            }

            // Get the apache port
            $port = $this->GetApachePort();

            if (!filter_var($dnsRecordIP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                if ($port === '443') {
                    throw new InvalidSettingConfigurationException("It seems like the ip-address of the domain is set to an internal or reserved ip-address. This is not supported by the domain validation. (It was found to be set to '" . $dnsRecordIP . "'). Please set it to a public ip-address so that the domain validation can work or skip the domain validation!");
                } else {
                    error_log("Info: It seems like the ip-address of " . $domain . " is set to an internal or reserved ip-address. (It was found to be set to '" . $dnsRecordIP . "')");
                }
            }

            // Check if port 443 is open
            $connection = @fsockopen($domain, 443, $errno, $errstr, 10);
            if ($connection) {
                fclose($connection);
            } else {
                throw new InvalidSettingConfigurationException("The domain is not reachable on Port 443 from within this container. Have you opened port 443/tcp in your router/firewall? If yes is the problem most likely that the router or firewall forbids local access to your domain. You can work around that by setting up a local DNS-server.");
            }

            // Get Instance ID
            $instanceID = $this->GetAndGenerateSecret('INSTANCE_ID');

            // set protocol
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
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = (string)curl_exec($ch);
            # Get rid of trailing \n
            $response = str_replace("\n", "", $response);

            if ($response !== $instanceID) {
                error_log('The response of the connection attempt to "' . $testUrl . '" was: ' . $response);
                error_log('Expected was: ' . $instanceID);
                error_log('The error message was: ' . curl_error($ch));
                $notice = "Domain does not point to this server or the reverse proxy is not configured correctly. See the mastercontainer logs for more details. ('sudo docker logs -f nextcloud-aio-mastercontainer')";
                if ($port === '443') {
                    $notice .= " If you should be using Cloudflare, make sure to disable the Cloudflare Proxy feature as it might block the domain validation. Same for any other firewall or service that blocks unencrypted access on port 443.";
                } else {
                    error_log('Please follow https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md#6-how-to-debug-things in order to debug things!');
                }
                throw new InvalidSettingConfigurationException($notice);
            }
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

    public function GetBaseDN() : string {
        $domain = $this->GetDomain();
        if ($domain === "") {
            return "";
        }
        return 'dc=' . implode(',dc=', explode('.', $domain));
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

    public function GetRestoreExcludePreviews() : string {
        $config = $this->GetConfig();
        if(!isset($config['restore-exclude-previews'])) {
            $config['restore-exclude-previews'] = '';
        }

        return $config['restore-exclude-previews'];
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
    public function SetBorgLocationVars(string $location, string $repo, string $remotePath = '') : void {
        $this->ValidateBorgLocationVars($location, $repo);

        $config = $this->GetConfig();
        $config['borg_backup_host_location'] = $location;
        $config['borg_remote_repo'] = $repo;
        $config['borg_remote_path'] = $remotePath;
        $this->WriteConfig($config);
    }

    private function ValidateBorgLocationVars(string $location, string $repo) : void {
        if ($location === '' && $repo === '') {
            throw new InvalidSettingConfigurationException("Please enter a path or a remote repo url!");
        } elseif ($location !== '' && $repo !== '') {
            throw new InvalidSettingConfigurationException("Location and remote repo url are mutually exclusive!");
        }

        if ($location !== '') {
            $isValidPath = false;
            if (str_starts_with($location, '/') && !str_ends_with($location, '/')) {
                $isValidPath = true;
            } elseif ($location === 'nextcloud_aio_backupdir') {
                $isValidPath = true;
            }

            if (!$isValidPath) {
                throw new InvalidSettingConfigurationException("The path must start with '/', and must not end with '/'! Another option is to use the docker volume name 'nextcloud_aio_backupdir'.");
            }

            // Prevent backup to be contained in Nextcloud Datadir as this will delete the backup archive upon restore
            // See https://github.com/nextcloud/all-in-one/issues/6607
            if (str_starts_with($location . '/', rtrim($this->GetNextcloudDatadirMount(), '/') . '/')) {
                throw new InvalidSettingConfigurationException("The path must not be a children of or equal to NEXTCLOUD_DATADIR, which is currently set to " . $this->GetNextcloudDatadirMount());
            }

        } else {
            $this->ValidateBorgRemoteRepo($repo);
        }
    }

    private function ValidateBorgRemoteRepo(string $repo) : void {
        $commonMsg = "For valid urls, see the remote examples at https://borgbackup.readthedocs.io/en/stable/usage/general.html#repository-urls";
        if ($repo === "") {
            // Ok, remote repo is optional
        } elseif (!str_contains($repo, "@")) {
            throw new InvalidSettingConfigurationException("The remote repo must contain '@'. $commonMsg");
        } elseif (!str_contains($repo, ":")) {
            throw new InvalidSettingConfigurationException("The remote repo must contain ':'. $commonMsg");
        }
    }

    public function DeleteBorgBackupLocationVars() : void {
        $config = $this->GetConfig();
        $config['borg_backup_host_location'] = '';
        $config['borg_remote_repo'] = '';
        $this->WriteConfig($config);
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetBorgRestoreLocationVarsAndPassword(string $location, string $repo, string $password, string $remotePath = '') : void {
        $this->ValidateBorgLocationVars($location, $repo);

        if ($password === '') {
            throw new InvalidSettingConfigurationException("Please enter the password!");
        }

        $config = $this->GetConfig();
        $config['borg_backup_host_location'] = $location;
        $config['borg_remote_repo'] = $repo;
        $config['borg_restore_password'] = $password;
        $config['borg_remote_path'] = $remotePath;
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

    public function GetTalkPort() : string {
        $envVariableName = 'TALK_PORT';
        $configName = 'talk_port';
        $defaultValue = '3478';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function WriteConfig(array $config) : void {
        if(!is_dir(DataConst::GetDataDirectory())) {
            throw new InvalidSettingConfigurationException(DataConst::GetDataDirectory() . " does not exist! Something was set up falsely!");
        }
        $df = disk_free_space(DataConst::GetDataDirectory());
        $content = json_encode($config, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        $size = strlen($content) + 10240;
        if ($df !== false && (int)$df < $size) {
            throw new InvalidSettingConfigurationException(DataConst::GetDataDirectory() . " does not have enough space for writing the config file! Not writing it back!");
        }
        file_put_contents(DataConst::GetConfigFile(), $content);
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

    public function GetBorgRemoteRepo() : string {
        $config = $this->GetConfig();
        if(!isset($config['borg_remote_repo'])) {
            $config['borg_remote_repo'] = '';
        }

        return $config['borg_remote_repo'];
    }

    public function GetBorgRemotePath() : string {
        $config = $this->GetConfig();
        if(!isset($config['borg_remote_path'])) {
            $config['borg_remote_path'] = '';
        }

        return $config['borg_remote_path'];
    }

    public function GetBorgPublicKey() : string {
        if (!file_exists(DataConst::GetBackupPublicKey())) {
            return "";
        }

        return trim(file_get_contents(DataConst::GetBackupPublicKey()));
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

    public function GetNextcloudUploadLimit() : string {
        $envVariableName = 'NEXTCLOUD_UPLOAD_LIMIT';
        $configName = 'nextcloud_upload_limit';
        $defaultValue = '16G';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function GetNextcloudMemoryLimit() : string {
        $envVariableName = 'NEXTCLOUD_MEMORY_LIMIT';
        $configName = 'nextcloud_memory_limit';
        $defaultValue = '512M';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function GetApacheMaxSize() : int {
        $uploadLimit = (int)rtrim($this->GetNextcloudUploadLimit(), 'G');
        return $uploadLimit * 1024 * 1024 * 1024;
    }

    public function GetNextcloudMaxTime() : string {
        $envVariableName = 'NEXTCLOUD_MAX_TIME';
        $configName = 'nextcloud_max_time';
        $defaultValue = '3600';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function GetBorgRetentionPolicy() : string {
        $envVariableName = 'BORG_RETENTION_POLICY';
        $configName = 'borg_retention_policy';
        $defaultValue = '--keep-within=7d --keep-weekly=4 --keep-monthly=6';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function GetFulltextsearchJavaOptions() : string {
        $envVariableName = 'FULLTEXTSEARCH_JAVA_OPTIONS';
        $configName = 'fulltextsearch_java_options';
        $defaultValue = '-Xms512M -Xmx512M';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function GetDockerSocketPath() : string {
        $envVariableName = 'WATCHTOWER_DOCKER_SOCKET_PATH';
        $configName = 'docker_socket_path';
        $defaultValue = '/var/run/docker.sock';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function GetTrustedCacertsDir() : string {
        $envVariableName = 'NEXTCLOUD_TRUSTED_CACERTS_DIR';
        $configName = 'trusted_cacerts_dir';
        $defaultValue = '';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function GetNextcloudAdditionalApks() : string {
        $envVariableName = 'NEXTCLOUD_ADDITIONAL_APKS';
        $configName = 'nextcloud_additional_apks';
        $defaultValue = 'imagemagick';
        return trim($this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue));
    }

    public function GetNextcloudAdditionalPhpExtensions() : string {
        $envVariableName = 'NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS';
        $configName = 'nextcloud_additional_php_extensions';
        $defaultValue = 'imagick';
        return trim($this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue));
    }

    public function GetCollaboraSeccompPolicy() : string {
        $defaultString = '--o:security.seccomp=';
        if ($this->GetCollaboraSeccompDisabledState() !== 'true') {
            return $defaultString . 'true';
        }
        return $defaultString . 'false';
    }

    private function GetCollaboraSeccompDisabledState() : string {
        $envVariableName = 'COLLABORA_SECCOMP_DISABLED';
        $configName = 'collabora_seccomp_disabled';
        $defaultValue = 'false';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetDailyBackupTime(string $time, bool $enableAutomaticUpdates, bool $successNotification) : void {
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

    public function GetDailyBackupTime() : string {
        if (!file_exists(DataConst::GetDailyBackupTimeFile())) {
            return '';
        }
        $dailyBackupFile = file_get_contents(DataConst::GetDailyBackupTimeFile());
        $dailyBackupFileArray = explode("\n", $dailyBackupFile);
        return $dailyBackupFileArray[0];
    }

    public function areAutomaticUpdatesEnabled() : bool {
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

    public function DeleteDailyBackupTime() : void {
        if (file_exists(DataConst::GetDailyBackupTimeFile())) {
            unlink(DataConst::GetDailyBackupTimeFile());
        }
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetAdditionalBackupDirectories(string $additionalBackupDirectories) : void {
        $additionalBackupDirectoriesArray = explode("\n", $additionalBackupDirectories);
        $validDirectories = '';
        foreach($additionalBackupDirectoriesArray as $entry) {
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

    public function shouldLatestMajorGetInstalled() : bool {
        $config = $this->GetConfig();
        if(!isset($config['install_latest_major'])) {
            $config['install_latest_major'] = '';
        }
        return $config['install_latest_major'] !== '';
    }

    public function GetAdditionalBackupDirectoriesString() : string {
        if (!file_exists(DataConst::GetAdditionalBackupDirectoriesFile())) {
            return '';
        }
        $additionalBackupDirectories = file_get_contents(DataConst::GetAdditionalBackupDirectoriesFile());
        return $additionalBackupDirectories;
    }

    public function GetAdditionalBackupDirectoriesArray() : array {
        $additionalBackupDirectories = $this->GetAdditionalBackupDirectoriesString();
        $additionalBackupDirectoriesArray = explode("\n", $additionalBackupDirectories);
        $additionalBackupDirectoriesArray = array_unique($additionalBackupDirectoriesArray, SORT_REGULAR);
        return $additionalBackupDirectoriesArray;
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

    public function shouldDomainValidationBeSkipped() : bool {
        if (getenv('SKIP_DOMAIN_VALIDATION') === 'true') {
            return true;
        }
        return false;
    }

    public function GetNextcloudStartupApps() : string {
        $apps = getenv('NEXTCLOUD_STARTUP_APPS');
        if (is_string($apps)) {
            return trim($apps);
        }
        return 'deck twofactor_totp tasks calendar contacts notes';
    }

    public function GetCollaboraDictionaries() : string {
        $config = $this->GetConfig();
        if(!isset($config['collabora_dictionaries'])) {
            $config['collabora_dictionaries'] = '';
        }

        return $config['collabora_dictionaries'];
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetCollaboraDictionaries(string $CollaboraDictionaries) : void {
        if ($CollaboraDictionaries === "") {
            throw new InvalidSettingConfigurationException("The dictionaries must not be empty!");
        }

        if (!preg_match("#^[a-zA-Z_ ]+$#", $CollaboraDictionaries)) {
            throw new InvalidSettingConfigurationException("The entered dictionaries do not seem to be a valid!");
        }

        $config = $this->GetConfig();
        $config['collabora_dictionaries'] = $CollaboraDictionaries;
        $this->WriteConfig($config);
    }

    public function DeleteCollaboraDictionaries() : void {
        $config = $this->GetConfig();
        $config['collabora_dictionaries'] = '';
        $this->WriteConfig($config);
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetAdditionalCollaboraOptions(string $additionalCollaboraOptions) : void {
        if ($additionalCollaboraOptions === "") {
            throw new InvalidSettingConfigurationException("The additional options must not be empty!");
        }

        if (!preg_match("#^--o:#", $additionalCollaboraOptions)) {
            throw new InvalidSettingConfigurationException("The entered options must start with '--o:'. So the config does not seem to be a valid!");
        }

        $config = $this->GetConfig();
        $config['collabora_additional_options'] = $additionalCollaboraOptions;
        $this->WriteConfig($config);
    }

    public function GetAdditionalCollaboraOptions() : string {
        $config = $this->GetConfig();
        if(!isset($config['collabora_additional_options'])) {
            $config['collabora_additional_options'] = '';
        }

        return $config['collabora_additional_options'];
    }

    public function DeleteAdditionalCollaboraOptions() : void {
        $config = $this->GetConfig();
        $config['collabora_additional_options'] = '';
        $this->WriteConfig($config);
    }

    public function GetApacheAdditionalNetwork() : string {
        $envVariableName = 'APACHE_ADDITIONAL_NETWORK';
        $configName = 'apache_additional_network';
        $defaultValue = '';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function GetApacheIPBinding() : string {
        $envVariableName = 'APACHE_IP_BINDING';
        $configName = 'apache_ip_binding';
        $defaultValue = '';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    private function GetDisableBackupSection() : string {
        $envVariableName = 'AIO_DISABLE_BACKUP_SECTION';
        $configName = 'disable_backup_section';
        $defaultValue = '';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function isBackupSectionEnabled() : bool {
        if ($this->GetDisableBackupSection() === 'true') {
            return false;
        } else {
            return true;
        }
    }

    private function GetCommunityContainers() : string {
        $config = $this->GetConfig();
        if(!isset($config['aio_community_containers'])) {
            $config['aio_community_containers'] = '';
        }

        return $config['aio_community_containers'];
    }


    public function listAvailableCommunityContainers() : array {
        $cc = [];
        $dir = scandir(DataConst::GetCommunityContainersDirectory());
        if ($dir === false) {
            return $cc;
        }
        // Get rid of dots from the scandir command
        $dir = array_diff($dir, array('..', '.', 'readme.md'));
        foreach ($dir as $id) {
            $filePath = DataConst::GetCommunityContainersDirectory() . '/' . $id . '/' . $id . '.json';
            $fileContents = apcu_fetch($filePath);
            if (!is_string($fileContents)) {
                $fileContents = file_get_contents($filePath);
                if (is_string($fileContents)) {
                    apcu_add($filePath, $fileContents);
                }
            } 
            $json = is_string($fileContents) ? json_decode($fileContents, true) : false;
            if(is_array($json) && is_array($json['aio_services_v1'])) {
                foreach ($json['aio_services_v1'] as $service) {
                    $documentation = is_string($service['documentation']) ? $service['documentation'] : '';
                    if (is_string($service['display_name'])) {
                        $cc[$id] = [ 
                            'id' => $id,
                            'name' => $service['display_name'],
                            'documentation' => $documentation
                        ];
                    }
                    break;
                }
            }
        }
        return $cc;
    }

    /** @return list<string> */
    public function GetEnabledCommunityContainers(): array {
        return explode(' ', $this->GetCommunityContainers());
    }

    public function SetEnabledCommunityContainers(array $enabledCommunityContainers) : void {
        $config = $this->GetConfig();
        $config['aio_community_containers'] = implode(' ', $enabledCommunityContainers);
        $this->WriteConfig($config);
    }

    private function GetEnabledDriDevice() : string {
        $envVariableName = 'NEXTCLOUD_ENABLE_DRI_DEVICE';
        $configName = 'nextcloud_enable_dri_device';
        $defaultValue = '';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function isDriDeviceEnabled() : bool {
        if ($this->GetEnabledDriDevice() === 'true') {
            return true;
        } else {
            return false;
        }
    }

    private function GetEnabledNvidiaGpu() : string {
        $envVariableName = 'NEXTCLOUD_ENABLE_NVIDIA_GPU';
        $configName = 'enable_nvidia_gpu';
        $defaultValue = '';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function isNvidiaGpuEnabled() : bool {
        return $this->GetEnabledNvidiaGpu() === 'true';
    }

    private function GetKeepDisabledApps() : string {
        $envVariableName = 'NEXTCLOUD_KEEP_DISABLED_APPS';
        $configName = 'nextcloud_keep_disabled_apps';
        $defaultValue = '';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function shouldDisabledAppsGetRemoved() : bool {
        if ($this->GetKeepDisabledApps() === 'true') {
            return false;
        } else {
            return true;
        }
    }
}
