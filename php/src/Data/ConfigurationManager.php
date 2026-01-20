<?php

namespace AIO\Data;

use AIO\Auth\PasswordGenerator;
use AIO\Controller\DockerController;

class ConfigurationManager
{
    private array $secrets = [];

    private array $config = [];

    private bool $noWrite = false;

    public string $AIO_TOKEN {
        get => $this->get('AIO_TOKEN', '');
        set { $this->set('AIO_TOKEN', $value); }
    }

    public string $password {
        get => $this->get('password', '');
        set { $this->set('password', $value); }
    }

    public bool $isDockerSocketProxyEnabled {
        get => $this->get('isDockerSocketProxyEnabled', false);
        set { $this->set('isDockerSocketProxyEnabled', $value); }
    }

    public bool $isWhiteboardEnabled {
        get => $this->get('isWhiteboardEnabled', true);
        set { $this->set('isWhiteboardEnabled', $value); }
    }

    public bool $restoreExcludePreviews {
        get => $this->get('restore-exclude-previews', false);
        set { $this->set('restore-exclude-previews', $value); }
    }

    public string $selectedRestoreTime {
        get => $this->get('selected-restore-time', '');
        set { $this->set('selected-restore-time', $value); }
    }

    public string $backupMode {
        get => $this->get('backup-mode', '');
        set { $this->set('backup-mode', $value); }
    }

    public bool $instance_restore_attempt {
        get => $this->get('instance_restore_attempt', false);
        set { $this->set('instance_restore_attempt', $value); }
    }

    public string $AIO_URL {
        get => $this->get('AIO_URL', '');
        set { $this->set('AIO_URL', $value); }
    }

    public bool $wasStartButtonClicked {
        get => $this->get('wasStartButtonClicked', false);
        set { $this->set('wasStartButtonClicked', $value); }
    }

    public bool $install_latest_major {
        get => $this->get('install_latest_major', false);
        set { $this->set('install_latest_major', $value); }
    }

    public bool $isClamavEnabled {
        get => $this->get('isClamavEnabled', false);
        set { $this->set('isClamavEnabled', $value); }
    }

    public bool $isOnlyofficeEnabled {
        get => $this->get('isOnlyofficeEnabled', false);
        set { $this->set('isOnlyofficeEnabled', $value); }
    }

    public bool $isCollaboraEnabled {
        get => $this->get('isCollaboraEnabled', true);
        set { $this->set('isCollaboraEnabled', $value); }
    }

    public bool $isTalkEnabled {
        get => $this->get('isTalkEnabled', true);
        set { $this->set('isTalkEnabled', $value); }
    }

    public bool $isTalkRecordingEnabled {
        get => $this->isTalkEnabled && $this->get('isTalkRecordingEnabled', false);
        set { $this->set('isTalkRecordingEnabled', $this->isTalkEnabled && $value); }
    }

    public bool $isImaginaryEnabled {
        get => $this->get('isImaginaryEnabled', true);
        set { $this->set('isImaginaryEnabled', $value); }
    }

    public bool $isFulltextsearchEnabled {
        get => $this->get('isFulltextsearchEnabled', false);
        // Elasticsearch does not work on kernels without seccomp anymore. See https://github.com/nextcloud/all-in-one/discussions/5768
        set { $this->set('isFulltextsearchEnabled', ($this->isSeccompDisabled() && $value)); }
    }

    public string $domain {
        get => $this->get('domain', '');
        set { $this->SetDomain($value); }
    }

    public string $borg_backup_host_location {
        get => $this->get('borg_backup_host_location', '');
        set { $this->set('borg_backup_host_location', $value); }
    }

    public string $borg_remote_repo {
        get => $this->get('borg_remote_repo', '');
        set { $this->set('borg_remote_repo', $value); }
    }

    public string $borg_restore_password {
        get => $this->get('borg_restore_password', '');
        set { $this->set('borg_restore_password', $value); }
    }

    public string $apache_ip_binding {
        get => $this->GetEnvironmentalVariableOrConfig('APACHE_IP_BINDING', 'apache_ip_binding', '');
        set { $this->set('apache_ip_binding', $value); }
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public string $timezone {
        get => $this->get('timezone', '');
        set {
            // This throws an exception if the validation fails.
            $this->validateTimezone($value);
            $this->set('timezone', $value);
        }
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public string $collabora_dictionaries {
        get => $this->get('collabora_dictionaries', '');
        set {
            // This throws an exception if the validation fails.
            $this->validateCollaboraDictionaries($value);
            $this->set('collabora_dictionaries', $value);
        }
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public string $collabora_additional_options {
        get => $this->get('collabora_additional_options', '');
        set {
            // This throws an exception if the validation fails.
            $this->validateCollaboraAdditionalOptions($value);
            $this->set('collabora_additional_options', $value);
        }
    }

    public array $aio_community_containers {
        get => explode(' ', $this->get('aio_community_containers', ''));
        set { $this->set('aio_community_containers', implode(' ', $value)); }
    }

    public string $turn_domain {
        get => $this->get('turn_domain', '');
        set { $this->set('turn_domain', $value); }
    }

    public function GetConfig() : array
    {
        if ($this->config === [] && file_exists(DataConst::GetConfigFile()))
        {
            $configContent = (string)file_get_contents(DataConst::GetConfigFile());
            $this->config = json_decode($configContent, true, 512, JSON_THROW_ON_ERROR);
        }

        return $this->config;
    }

    private function get(string $key, mixed $fallbackValue = null) : mixed {
        return $this->GetConfig()[$key] ?? $fallbackValue;
    }

    private function set(string $key, mixed $value) : void {
        $this->GetConfig();
        $this->config[$key] = $value;
        // Only write if this isn't called via setMultiple().
        if ($this->noWrite !== true) {
            $this->WriteConfig();
        }
    }

    /**
     * This allows to assign multiple attributes without saving the config to disk in between (as would
     * calling set() do).
     */
    public function setMultiple(\Closure $closure) : void {
        $this->noWrite = true;
        try {
            $this->GetConfig();
            $closure($this);
            $this->WriteConfig();
        } finally {
            $this->noWrite = false;
        }
    }

    public function GetAndGenerateSecret(string $secretId) : string {
        if ($secretId === '') {
            return '';
        }

        $secrets = $this->get('secrets', []);
        if (!isset($secrets[$secretId])) {
            $secrets[$secretId] = bin2hex(random_bytes(24));
            $this->set('secrets', $secrets);
        }

        if ($secretId === 'BORGBACKUP_PASSWORD' && !file_exists(DataConst::GetBackupSecretFile())) {
            $this->DoubleSafeBackupSecret($secrets[$secretId]);
        }

        return $secrets[$secretId];
    }

    public function GetRegisteredSecret(string $secretId) : string {
        if ($this->secrets[$secretId]) {
            return $this->GetAndGenerateSecret($secretId);
        }
        throw new \Exception("The secret " . $secretId . " was not registered. Please check if it is defined in secrets of containers.json.");
    }

    public function RegisterSecret(string $secretId) : void {
        $this->secrets[$secretId] = true;
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

        $content = (string)file_get_contents(DataConst::GetBackupArchivesList());

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

        $content = (string)file_get_contents(DataConst::GetBackupArchivesList());

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

    private function isx64Platform() : bool {
        if (php_uname('m') === 'x86_64') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @throws InvalidSettingConfigurationException
     *
     * We can't turn this into a private validation method because of the second argument.
     */
    public function SetDomain(string $domain, bool $skipDomainValidation) : void {
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
        if ($this->shouldDomainValidationBeSkipped($skipDomainValidation)) {
            error_log('Skipping domain validation');
        } else {
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
            $port = $this->apache_port;

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
                throw new InvalidSettingConfigurationException("The domain is not reachable on Port 443 from within this container. Have you opened port 443/tcp in your router/firewall? If yes is the problem most likely that the router or firewall forbids local access to your domain. Or in other words: NAT loopback (Hairpinning) does not seem to work in your network. You can work around that by setting up a local DNS server and utilizing Split-Brain-DNS and configuring the daemon.json file of your docker daemon to use the local DNS server.");
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
            if ($ch === false) {
                throw new InvalidSettingConfigurationException('Could not init curl! Please check the logs!');
            }
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
                    error_log('Please follow https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md#how-to-debug in order to debug things!');
                }
                throw new InvalidSettingConfigurationException($notice);
            }
        }

        $config = $this->GetConfig();
        $this->WriteConfig($config);
        $this->setMultiple(function ($confManager) use ($domain) {
            // Write domain
            // Don't set the domain via the attribute, or we create a loop.
            $confManager->set('domain', $domain);
            // Reset the borg restore password when setting the domain
            $confManager->borg_restore_password = '';
        });
    }

    public function GetBaseDN() : string {
        $domain = $this->domain;
        if ($domain === "") {
            return "";
        }
        return 'dc=' . implode(',dc=', explode('.', $domain));
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetBorgLocationVars(string $location, string $repo) : void {
        $this->ValidateBorgLocationVars($location, $repo);

        $config = $this->GetConfig();
        $this->WriteConfig($config);
        $this->setMultiple(function ($confManager) use ($location, $repo) {
            $confManager->borg_backup_host_location = $location;
            $confManager->borg_remote_repo = $repo;
        });
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
            if (str_starts_with($location . '/', rtrim($this->nextcloud_datadir_mount, '/') . '/')) {
                throw new InvalidSettingConfigurationException("The path must not be a children of or equal to NEXTCLOUD_DATADIR, which is currently set to " . $this->nextcloud_datadir_mount);
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

    public function DeleteBorgBackupLocationItems() : void {
        // Delete the variables
        $config = $this->GetConfig();
        $this->WriteConfig($config);
        $this->setMultiple(function ($confManager) {
            $confManager->borg_backup_host_location = '';
            $confManager->borg_remote_repo = '';
        });

        // Also delete the borg config file to be able to start over
        if (file_exists(DataConst::GetBackupKeyFile())) {
            if (unlink(DataConst::GetBackupKeyFile())) {
                error_log('borg.config file deleted to be able to start over.');
            }
        }
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function SetBorgRestoreLocationVarsAndPassword(string $location, string $repo, string $password) : void {
        $this->ValidateBorgLocationVars($location, $repo);

        if ($password === '') {
            throw new InvalidSettingConfigurationException("Please enter the password!");
        }

        $config = $this->GetConfig();
        $this->WriteConfig($config);
        $this->setMultiple(function ($confManager) use ($location, $repo, $password) {
            $confManager->borg_backup_host_location = $location;
            $confManager->borg_remote_repo = $repo;
            $confManager->borg_restore_password = $password;
            $confManager->instance_restore_attempt = true;
        });
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function ChangeMasterPassword(string $currentPassword, string $newPassword) : void {
        if ($currentPassword === '') {
            throw new InvalidSettingConfigurationException("Please enter your current password.");
        }

        if ($currentPassword !== $this->password) {
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
        $this->set('password', $newPassword);
    }

    public string $apache_port {
        get => $this->GetEnvironmentalVariableOrConfig('APACHE_PORT', 'apache_port', '443');
        set { $this->set('apache_port', $value); }
    }
        
    public string $talk_port {
        get => $this->GetEnvironmentalVariableOrConfig('TALK_PORT', 'talk_port', '3478');
        set { $this->set('talk_port', $value); }
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function WriteConfig(?array $config) : void {
        if ($config) {
            $this->config = $config;
        }
        if(!is_dir(DataConst::GetDataDirectory())) {
            throw new InvalidSettingConfigurationException(DataConst::GetDataDirectory() . " does not exist! Something was set up falsely!");
        }
        // Shouldn't happen, but as a precaution we won't write an empty config to disk.
        if ($this->config === []) {
            return;
        }
        $df = disk_free_space(DataConst::GetDataDirectory());
        $content = json_encode($this->config, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
        $size = strlen($content) + 10240;
        if ($df !== false && (int)$df < $size) {
            throw new InvalidSettingConfigurationException(DataConst::GetDataDirectory() . " does not have enough space for writing the config file! Not writing it back!");
        }
        file_put_contents(DataConst::GetConfigFile(), $content);
        $this->config = [];
    }

    private function GetEnvironmentalVariableOrConfig(string $envVariableName, string $configName, string $defaultValue) : string {
        $envVariableOutput = getenv($envVariableName);
        $configValue = $this->get($configName, '');
        if ($envVariableOutput === false) {
            if ($configValue === '') {
                $this->set($configName, $defaultValue);
                return $defaultValue;
            }
            return $configValue;
        }

        if (file_exists(DataConst::GetConfigFile())) {
            if ($envVariableOutput !== $configValue) {
                $this->set($configName, $envVariableOutput);
            }
        }

        return $envVariableOutput;
    }

    public function GetBorgPublicKey() : string {
        if (!file_exists(DataConst::GetBackupPublicKey())) {
            return "";
        }

        return trim((string)file_get_contents(DataConst::GetBackupPublicKey()));
    }

    public string $nextcloud_mount {
        get => $this->GetEnvironmentalVariableOrConfig('NEXTCLOUD_MOUNT', 'nextcloud_mount', '');
        set { $this->set('nextcloud_mount', $value); }
    }


    public string $nextcloud_datadir_mount {
        get => $this->GetEnvironmentalVariableOrConfig('NEXTCLOUD_DATADIR', 'nextcloud_datadir', 'nextcloud_aio_nextcloud_data');
        set { $this->set('nextcloud_datadir_mount', $value); }
    }

    public string $nextcloud_upload_limit {
        get => $this->GetEnvironmentalVariableOrConfig('NEXTCLOUD_UPLOAD_LIMIT', 'nextcloud_upload_limit', '16G');
        set { $this->set('nextcloud_upload_limit', $value); }
    }
    public function GetNextcloudMemoryLimit() : string {
        $envVariableName = 'NEXTCLOUD_MEMORY_LIMIT';
        $configName = 'nextcloud_memory_limit';
        $defaultValue = '512M';
        return $this->GetEnvironmentalVariableOrConfig($envVariableName, $configName, $defaultValue);
    }

    public function GetApacheMaxSize() : int {
        $uploadLimit = (int)rtrim($this->nextcloud_upload_limit, 'G');
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
        if (!$this->isSeccompDisabled()) {
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

    public function isSeccompDisabled() : bool {
        if ($this->GetCollaboraSeccompDisabledState() === 'true') {
            return true;
        }
        return false;
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
        $dailyBackupFile = (string)file_get_contents(DataConst::GetDailyBackupTimeFile());
        $dailyBackupFileArray = explode("\n", $dailyBackupFile);
        return $dailyBackupFileArray[0];
    }

    public function areAutomaticUpdatesEnabled() : bool {
        if (!file_exists(DataConst::GetDailyBackupTimeFile())) {
            return false;
        }
        $dailyBackupFile = (string)file_get_contents(DataConst::GetDailyBackupTimeFile());
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

    public function GetAdditionalBackupDirectoriesString() : string {
        if (!file_exists(DataConst::GetAdditionalBackupDirectoriesFile())) {
            return '';
        }
        return (string)file_get_contents(DataConst::GetAdditionalBackupDirectoriesFile());
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

    /**
     * @throws InvalidSettingConfigurationException
     */
    private function validateTimezone(string $timezone) : void {
        if ($timezone === "") {
            throw new InvalidSettingConfigurationException("The timezone must not be empty!");
        }

        if (!preg_match("#^[a-zA-Z0-9_\-\/\+]+$#", $timezone)) {
            throw new InvalidSettingConfigurationException("The entered timezone does not seem to be a valid timezone!");
        }
    }

    /**
     * Provide an extra method since our `timezone` attribute setter prevents setting an empty timezone.
     */
    public function deleteTimezone() : void {
        $this->set('timezone', '');
    }

    public function shouldDomainValidationBeSkipped(bool $skipDomainValidation) : bool {
        if ($skipDomainValidation || getenv('SKIP_DOMAIN_VALIDATION') === 'true') {
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

    /**
     * @throws InvalidSettingConfigurationException
     */
    private function validateCollaboraDictionaries(string $CollaboraDictionaries) : void {
        if ($CollaboraDictionaries === "") {
            throw new InvalidSettingConfigurationException("The dictionaries must not be empty!");
        }

        if (!preg_match("#^[a-zA-Z_ ]+$#", $CollaboraDictionaries)) {
            throw new InvalidSettingConfigurationException("The entered dictionaries do not seem to be a valid!");
        }
    }

    /**
     * Provide an extra method since the corresponding attribute setter prevents setting an empty value.
     */
    public function DeleteCollaboraDictionaries() : void {
        $this->set('collabora_dictionaries', '');
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    private function validateCollaboraAdditionalOptions(string $additionalCollaboraOptions) : void {
        if ($additionalCollaboraOptions === "") {
            throw new InvalidSettingConfigurationException("The additional options must not be empty!");
        }

        if (!preg_match("#^--o:#", $additionalCollaboraOptions)) {
            throw new InvalidSettingConfigurationException("The entered options must start with '--o:'. So the config does not seem to be a valid!");
        }
    }

    public function isCollaboraSubscriptionEnabled() : bool {
        if (str_contains($this->collabora_additional_options, '--o:support_key=')) {
            return true;
        }
        return false;
    }

    /**
     * Provide an extra method since the corresponding attribute setter prevents setting an empty value.
     */
    public function deleteAdditionalCollaboraOptions() : void {
        $this->set('collabora_additional_options', '');
    }

    public function GetApacheAdditionalNetwork() : string {
        $envVariableName = 'APACHE_ADDITIONAL_NETWORK';
        $configName = 'apache_additional_network';
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
            $json = is_string($fileContents) ? json_decode($fileContents, true, 512, JSON_THROW_ON_ERROR) : false;
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
