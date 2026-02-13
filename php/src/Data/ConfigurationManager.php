<?php
declare(strict_types=1);

namespace AIO\Data;

use AIO\Auth\PasswordGenerator;
use AIO\Controller\DockerController;

class ConfigurationManager
{
    private array $secrets = [];

    private array $config = [];

    private bool $noWrite = false;

    public string $aioToken {
        get => $this->get('AIO_TOKEN', '');
        set { $this->set('AIO_TOKEN', $value); }
    }

    public string $password {
        get => $this->get('password', '');
        set { $this->set('password', $value); }
    }

    public bool $isDockerSocketProxyEnabled {
        // Type-cast because old configs could have 1/0 for this key.
        get => (bool) $this->get('isDockerSocketProxyEnabled', false);
        set { $this->set('isDockerSocketProxyEnabled', $value); }
    }

    public bool $isWhiteboardEnabled {
        // Type-cast because old configs could have 1/0 for this key.
        get => (bool) $this->get('isWhiteboardEnabled', true);
        set { $this->set('isWhiteboardEnabled', $value); }
    }

    public bool $restoreExcludePreviews {
        // Type-cast because old configs could have '1'/'' for this key.
        get => (bool) $this->get('restore-exclude-previews', false);
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

    public bool $instanceRestoreAttempt {
        // Type-cast because old configs could have 1/'' for this key.
        get => (bool) $this->get('instance_restore_attempt', false);
        set { $this->set('instance_restore_attempt', $value); }
    }

    public string $aioUrl {
        get => $this->get('AIO_URL', '');
        set { $this->set('AIO_URL', $value); }
    }

    public bool $wasStartButtonClicked {
        // Type-cast because old configs could have 1/0 for this key.
        get => (bool) $this->get('wasStartButtonClicked', false);
        set { $this->set('wasStartButtonClicked', $value); }
    }

    public string $installLatestMajor {
        // Type-cast because old configs could have integers for this key.
        get => (string) $this->get('install_latest_major', '');
        set { $this->set('install_latest_major', $value); }
    }

    public bool $isClamavEnabled {
        // Type-cast because old configs could have 1/0 for this key.
        get => (bool) $this->get('isClamavEnabled', false);
        set { $this->set('isClamavEnabled', $value); }
    }

    public bool $isOnlyofficeEnabled {
        // Type-cast because old configs could have 1/0 for this key.
        get => (bool) $this->get('isOnlyofficeEnabled', false);
        set { $this->set('isOnlyofficeEnabled', $value); }
    }

    public bool $isCollaboraEnabled {
        // Type-cast because old configs could have 1/0 for this key.
        get => (bool) $this->get('isCollaboraEnabled', true);
        set { $this->set('isCollaboraEnabled', $value); }
    }

    public bool $isTalkEnabled {
        // Type-cast because old configs could have 1/0 for this key.
        get => (bool) $this->get('isTalkEnabled', true);
        set { $this->set('isTalkEnabled', $value); }
    }

    public bool $isTalkRecordingEnabled {
        // Type-cast because old configs could have 1/0 for this key.
        get => (bool) $this->isTalkEnabled && $this->get('isTalkRecordingEnabled', false);
        set { $this->set('isTalkRecordingEnabled', $this->isTalkEnabled && $value); }
    }

    public bool $isImaginaryEnabled {
        // Type-cast because old configs could have 1/0 for this key.
        get => (bool) $this->get('isImaginaryEnabled', true);
        set { $this->set('isImaginaryEnabled', $value); }
    }

    public bool $isFulltextsearchEnabled {
        // Type-cast because old configs could have 1/0 for this key.
        get => (bool) $this->get('isFulltextsearchEnabled', false);
        // Elasticsearch does not work on kernels without seccomp anymore. See https://github.com/nextcloud/all-in-one/discussions/5768
        set { $this->set('isFulltextsearchEnabled', (!$this->collaboraSeccompDisabled && $value)); }
    }

    public string $domain {
        get => $this->get('domain', '');
        set { $this->setDomain($value); }
    }

    public string $borgBackupHostLocation {
        get => $this->get('borg_backup_host_location', '');
        set { $this->set('borg_backup_host_location', $value); }
    }

    public string $borgRemoteRepo {
        get => $this->get('borg_remote_repo', '');
        set { $this->set('borg_remote_repo', $value); }
    }

    public string $borgRestorePassword {
        get => $this->get('borg_restore_password', '');
        set { $this->set('borg_restore_password', $value); }
    }

    public string $apacheIpBinding {
        get => $this->getEnvironmentalVariableOrConfig('APACHE_IP_BINDING', 'apache_ip_binding', '');
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
    public string $collaboraDictionaries {
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
    public string $collaboraAdditionalOptions {
        get => $this->get('collabora_additional_options', '');
        set {
            // This throws an exception if the validation fails.
            $this->validateCollaboraAdditionalOptions($value);
            $this->set('collabora_additional_options', $value);
        }
    }

    public array $aioCommunityContainers {
        get => explode(' ', $this->get('aio_community_containers', ''));
        set { $this->set('aio_community_containers', implode(' ', $value)); }
    }

    public string $turnDomain {
        get => $this->get('turn_domain', '');
        set { $this->set('turn_domain', $value); }
    }

    public string $apachePort {
        get => $this->getEnvironmentalVariableOrConfig('APACHE_PORT', 'apache_port', '443');
        set { $this->set('apache_port', $value); }
    }

    public string $talkPort {
        get => $this->getEnvironmentalVariableOrConfig('TALK_PORT', 'talk_port', '3478');
        set { $this->set('talk_port', $value); }
    }

    public string $nextcloudMount {
        get => $this->getEnvironmentalVariableOrConfig('NEXTCLOUD_MOUNT', 'nextcloud_mount', '');
        set { $this->set('nextcloud_mount', $value); }
    }

    public string $nextcloudDatadirMount {
        get => $this->getEnvironmentalVariableOrConfig('NEXTCLOUD_DATADIR', 'nextcloud_datadir', 'nextcloud_aio_nextcloud_data');
        set { $this->set('nextcloud_datadir_mount', $value); }
    }

    public string $nextcloudUploadLimit {
        get => $this->getEnvironmentalVariableOrConfig('NEXTCLOUD_UPLOAD_LIMIT', 'nextcloud_upload_limit', '16G');
        set { $this->set('nextcloud_upload_limit', $value); }
    }

    public string $nextcloudMemoryLimit {
        get => $this->getEnvironmentalVariableOrConfig('NEXTCLOUD_MEMORY_LIMIT', 'nextcloud_memory_limit', '512M');
        set { $this->set('nextcloud_memory_limit', $value); }
    }

    public function getApacheMaxSize() : int {
        $uploadLimit = (int)rtrim($this->nextcloudUploadLimit, 'G');
        return $uploadLimit * 1024 * 1024 * 1024;
    }

    public string $nextcloudMaxTime {
        get => $this->getEnvironmentalVariableOrConfig('NEXTCLOUD_MAX_TIME', 'nextcloud_max_time', '3600');
        set { $this->set('nextcloud_max_time', $value); }
    }

    public string $borgRetentionPolicy {
        get => $this->getEnvironmentalVariableOrConfig('BORG_RETENTION_POLICY', 'borg_retention_policy', '--keep-within=7d --keep-weekly=4 --keep-monthly=6');
        set { $this->set('borg_retention_policy', $value); }
    }

    public string $fulltextsearchJavaOptions {
        get => $this->getEnvironmentalVariableOrConfig('FULLTEXTSEARCH_JAVA_OPTIONS', 'fulltextsearch_java_options', '-Xms512M -Xmx512M');
        set { $this->set('fulltextsearch_java_options', $value); }
    }

    public string $dockerSocketPath {
        get => $this->getEnvironmentalVariableOrConfig('WATCHTOWER_DOCKER_SOCKET_PATH', 'docker_socket_path', '/var/run/docker.sock');
        set { $this->set('docker_socket_path', $value); }
    }

    public string $trustedCacertsDir {
        get => $this->getEnvironmentalVariableOrConfig('NEXTCLOUD_TRUSTED_CACERTS_DIR', 'trusted_cacerts_dir', '');
        set { $this->set('trusted_cacerts_dir', $value); }
    }

    public string $nextcloudAdditionalApks {
        get => trim($this->getEnvironmentalVariableOrConfig('NEXTCLOUD_ADDITIONAL_APKS', 'nextcloud_additional_apks', 'imagemagick'));
        set { $this->set('nextcloud_addtional_apks', $value); }
    }

    public string $nextcloudAdditionalPhpExtensions {
        get => trim($this->getEnvironmentalVariableOrConfig('NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS', 'nextcloud_additional_php_extensions', 'imagick'));
        set { $this->set('nextcloud_additional_php_extensions', $value); }
    }

    public bool $collaboraSeccompDisabled {
        get => $this->booleanize($this->getEnvironmentalVariableOrConfig('COLLABORA_SECCOMP_DISABLED', 'collabora_seccomp_disabled', ''));
        set { $this->set('collabora_seccomp_disabled', $value); }
    }

    public bool $disableBackupSection {
        get => $this->booleanize($this->getEnvironmentalVariableOrConfig('AIO_DISABLE_BACKUP_SECTION', 'disable_backup_section', ''));
        set { $this->set('disable_backup_section', $value); }
    }

    public bool $nextcloudEnableDriDevice{
        get => $this->booleanize($this->getEnvironmentalVariableOrConfig('NEXTCLOUD_ENABLE_DRI_DEVICE', 'nextcloud_enable_dri_device', ''));
        set { $this->set('nextcloud_enable_dri_device', $value); }
    }

    public bool $enableNvidiaGpu {
        get => $this->booleanize($this->getEnvironmentalVariableOrConfig('NEXTCLOUD_ENABLE_NVIDIA_GPU', 'enable_nvidia_gpu', ''));
        set { $this->set('enable_nvidia_gpu', $value); }
    }

    public bool $nextcloudKeepDisabledApps {
        get => $this->booleanize($this->getEnvironmentalVariableOrConfig('NEXTCLOUD_KEEP_DISABLED_APPS', 'nextcloud_keep_disabled_apps', ''));
        set { $this->set('nextcloud_keep_disabled_apps', $value); }
    }

    private function getConfig() : array
    {
        if ($this->config === [] && file_exists(DataConst::GetConfigFile()))
        {
            $configContent = (string)file_get_contents(DataConst::GetConfigFile());
            $this->config = json_decode($configContent, true, 512, JSON_THROW_ON_ERROR);
        }

        return $this->config;
    }

    private function get(string $key, mixed $fallbackValue = null) : mixed {
        return $this->getConfig()[$key] ?? $fallbackValue;
    }

    private function set(string $key, mixed $value) : void {
        $this->getConfig();
        $this->config[$key] = $value;
        // Only write if this isn't called in between startTransaction() and commitTransaction().
        if ($this->noWrite !== true) {
            $this->writeConfig();
        }
    }

    /**
     * This allows to assign multiple attributes without saving the config to disk in between. It must be
     * followed by a call to commitTransaction(), which then writes all changes to disk.
     */
    public function startTransaction() : void {
        $this->getConfig();
        $this->noWrite = true;
    }

    /**
     * This allows to assign multiple attributes without saving the config to disk in between.
     */
    public function commitTransaction() : void {
        try {
            $this->writeConfig();
        } finally {
            $this->noWrite = false;
        }
    }

    public function getAndGenerateSecret(string $secretId) : string {
        if ($secretId === '') {
            return '';
        }

        $secrets = $this->get('secrets', []);
        if (!isset($secrets[$secretId])) {
            $secrets[$secretId] = bin2hex(random_bytes(24));
            $this->set('secrets', $secrets);
        }

        if ($secretId === 'BORGBACKUP_PASSWORD' && !file_exists(DataConst::GetBackupSecretFile())) {
            $this->doubleSafeBackupSecret($secrets[$secretId]);
        }

        return $secrets[$secretId];
    }

    public function getRegisteredSecret(string $secretId) : string {
        if ($this->secrets[$secretId]) {
            return $this->getAndGenerateSecret($secretId);
        }
        throw new \Exception("The secret " . $secretId . " was not registered. Please check if it is defined in secrets of containers.json.");
    }

    public function registerSecret(string $secretId) : void {
        $this->secrets[$secretId] = true;
    }

    private function doubleSafeBackupSecret(string $borgBackupPassword) : void {
        file_put_contents(DataConst::GetBackupSecretFile(), $borgBackupPassword);
    }

    public function hasBackupRunOnce() : bool {
        if (!file_exists(DataConst::GetBackupKeyFile())) {
            return false;
        } else {
            return true;
        }
    }

    public function getLastBackupTime() : string {
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

    public function getBackupTimes() : array {
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

    public function getAioVersion() : string {
        $path = DataConst::GetAioVersionFile();
        if ($path !== '' && file_exists($path)) {
            return trim((string)file_get_contents($path));
        }
        return '';
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
    public function setDomain(string $domain, bool $skipDomainValidation) : void {
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
            $port = $this->apachePort;

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
            $instanceID = $this->getAndGenerateSecret('INSTANCE_ID');

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

        $this->startTransaction();
        // Write domain
        // Don't set the domain via the attribute, or we create a loop.
        $this->set('domain', $domain);
        // Reset the borg restore password when setting the domain
        $this->borgRestorePassword = '';
        $this->startTransaction();
        $this->commitTransaction();
    }

    public function getBaseDN() : string {
        $domain = $this->domain;
        if ($domain === "") {
            return "";
        }
        return 'dc=' . implode(',dc=', explode('.', $domain));
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function setBorgLocationVars(string $location, string $repo) : void {
        $this->validateBorgLocationVars($location, $repo);
        $this->startTransaction();
        $this->borgBackupHostLocation = $location;
        $this->borgRemoteRepo = $repo;
        $this->commitTransaction();
    }

    private function validateBorgLocationVars(string $location, string $repo) : void {
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
            if (str_starts_with($location . '/', rtrim($this->nextcloudDatadirMount, '/') . '/')) {
                throw new InvalidSettingConfigurationException("The path must not be a children of or equal to NEXTCLOUD_DATADIR, which is currently set to " . $this->nextcloudDatadirMount);
            }

        } else {
            $this->validateBorgRemoteRepo($repo);
        }
    }

    private function validateBorgRemoteRepo(string $repo) : void {
        $commonMsg = "For valid urls, see the remote examples at https://borgbackup.readthedocs.io/en/stable/usage/general.html#repository-urls";
        if ($repo === "") {
            // Ok, remote repo is optional
        } elseif (!str_contains($repo, "@")) {
            throw new InvalidSettingConfigurationException("The remote repo must contain '@'. $commonMsg");
        } elseif (!str_contains($repo, ":")) {
            throw new InvalidSettingConfigurationException("The remote repo must contain ':'. $commonMsg");
        }
    }

    public function deleteBorgBackupLocationItems() : void {
        // Delete the variables
        $this->startTransaction();
        $this->borgBackupHostLocation = '';
        $this->borgRemoteRepo = '';
        $this->commitTransaction();

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
    public function setBorgRestoreLocationVarsAndPassword(string $location, string $repo, string $password) : void {
        $this->validateBorgLocationVars($location, $repo);

        if ($password === '') {
            throw new InvalidSettingConfigurationException("Please enter the password!");
        }

        $this->startTransaction();
        $this->borgBackupHostLocation = $location;
        $this->borgRemoteRepo = $repo;
        $this->borgRestorePassword = $password;
        $this->instanceRestoreAttempt = true;
        $this->commitTransaction();
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function changeMasterPassword(string $currentPassword, string $newPassword) : void {
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

    /**
     * @throws InvalidSettingConfigurationException
     */
    private function writeConfig() : void {
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

    private function getEnvironmentalVariableOrConfig(string $envVariableName, string $configName, string $defaultValue) : string {
        $envVariableOutput = getenv($envVariableName);
        $configValue = $this->get($configName, '');
        if ($envVariableOutput === false) {
            if ($configValue === '') {
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

    public function getBorgPublicKey() : string {
        if (!file_exists(DataConst::GetBackupPublicKey())) {
            return "";
        }

        return trim((string)file_get_contents(DataConst::GetBackupPublicKey()));
    }

    public function getCollaboraSeccompPolicy() : string {
        $defaultString = '--o:security.seccomp=';
        if (!$this->collaboraSeccompDisabled) {
            return $defaultString . 'true';
        }
        return $defaultString . 'false';
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function setDailyBackupTime(string $time, bool $enableAutomaticUpdates, bool $successNotification) : void {
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

    public function getDailyBackupTime() : string {
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

    public function deleteDailyBackupTime() : void {
        if (file_exists(DataConst::GetDailyBackupTimeFile())) {
            unlink(DataConst::GetDailyBackupTimeFile());
        }
    }

    /**
     * @throws InvalidSettingConfigurationException
     */
    public function setAdditionalBackupDirectories(string $additionalBackupDirectories) : void {
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

    public function getAdditionalBackupDirectoriesString() : string {
        if (!file_exists(DataConst::GetAdditionalBackupDirectoriesFile())) {
            return '';
        }
        return (string)file_get_contents(DataConst::GetAdditionalBackupDirectoriesFile());
    }

    public function getAdditionalBackupDirectoriesArray() : array {
        $additionalBackupDirectories = $this->getAdditionalBackupDirectoriesString();
        $additionalBackupDirectoriesArray = explode("\n", $additionalBackupDirectories);
        $additionalBackupDirectoriesArray = array_unique($additionalBackupDirectoriesArray, SORT_REGULAR);
        return $additionalBackupDirectoriesArray;
    }

    public function isDailyBackupRunning() : bool {
        return file_exists(DataConst::GetDailyBackupBlockFile());
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

    public function getApacheAdditionalNetwork() : string {
        $network = getenv('APACHE_ADDITIONAL_NETWORK');
        if (is_string($network)) {
            return trim($network);
        }
        return '';
    }

    public function getNextcloudStartupApps() : string {
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
    public function deleteCollaboraDictionaries() : void {
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
        return str_contains($this->collaboraAdditionalOptions, '--o:support_key=');
    }

    /**
     * Provide an extra method since the corresponding attribute setter prevents setting an empty value.
     */
    public function deleteAdditionalCollaboraOptions() : void {
        $this->set('collabora_additional_options', '');
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

    private function camelize(string $input, string $delimiter = '_') : string {
        if ($input === '') {
            throw new InvalidSettingConfigurationException('input cannot be empty!');
        }
        if ($delimiter === '') {
            $delimiter = '_';
        }
        return lcfirst(implode("", array_map('ucfirst', explode($delimiter, strtolower($input)))));

    }

    public function setAioVariables(array $input) : void {
        if ($input === []) {
            return;
        }
        $this->startTransaction();
        foreach ($input as $variable) {
            if (!is_string($variable) || !str_contains($variable, '=')) {
                error_log("Invalid input: '$variable' is not a string or does not contain an equal sign ('=')");
                continue;
            }
            $keyWithValue = $this->replaceEnvPlaceholders($variable);
            // Pad the result with nulls so psalm is happy (and we don't risk to run into warnings in case
            // the check for an equal sign from above gets changed).
            [$key, $value] = explode('=', $keyWithValue, 2) + [null, null];
            $key = $this->camelize($key);
            if ($value === null) {
                error_log("Invalid input: '$keyWithValue' has no value after the equal sign");
            } else if (!property_exists($this, $key)) {
                error_log("Error: '$key' is not a valid configuration key (in '$keyWithValue')");
            } else {
                $this->$key = $value;
            }
        }
        $this->commitTransaction();
    }

    //
    // Replaces placeholders in $envValue with their values.
    // E.g. "%NC_DOMAIN%:%APACHE_PORT" becomes "my.nextcloud.com:11000"
    public function replaceEnvPlaceholders(string $envValue): string {
        // $pattern breaks down as:
        // % - matches a literal percent sign
        // ([^%]+) - capture group that matches one or more characters that are NOT percent signs
        // % - matches the closing percent sign
        //
        // Assumes literal percent signs are always matched and there is no
        // escaping.
        $pattern = '/%([^%]+)%/';
        $matchCount = preg_match_all($pattern, $envValue, $matches);

        if ($matchCount === 0) {
            return $envValue;
        }

        $placeholders = $matches[0]; // ["%PLACEHOLDER1%", "%PLACEHOLDER2%", ...]
        $placeholderNames = $matches[1]; // ["PLACEHOLDER1", "PLACEHOLDER2", ...]
        $placeholderPatterns = array_map(static fn(string $p) => '/' . preg_quote($p) . '/', $placeholders); // ["/%PLACEHOLDER1%/", ...]
        $placeholderValues = array_map($this->getPlaceholderValue(...), $placeholderNames); // ["val1", "val2"]
        // Guaranteed to be non-null because we found the placeholders in the preg_match_all.
        return (string) preg_replace($placeholderPatterns, $placeholderValues, $envValue);
    }

    private function getPlaceholderValue(string $placeholder) : string {
        return match ($placeholder) {
            'NC_DOMAIN' => $this->domain,
            'NC_BASE_DN' => $this->getBaseDN(),
            'AIO_TOKEN' => $this->aioToken,
            'BORGBACKUP_REMOTE_REPO' => $this->borgRemoteRepo,
            'BORGBACKUP_MODE' => $this->backupMode,
            'AIO_URL' => $this->aioUrl,
            'SELECTED_RESTORE_TIME' => $this->selectedRestoreTime,
            'RESTORE_EXCLUDE_PREVIEWS' => $this->restoreExcludePreviews ? '1' : '',
            'APACHE_PORT' => $this->apachePort,
            'APACHE_IP_BINDING' => $this->apacheIpBinding,
            'TALK_PORT' => $this->talkPort,
            'TURN_DOMAIN' => $this->turnDomain,
            'NEXTCLOUD_MOUNT' => $this->nextcloudMount,
            'BACKUP_RESTORE_PASSWORD' => $this->borgRestorePassword,
            'CLAMAV_ENABLED' => $this->isClamavEnabled ? 'yes' : '',
            'TALK_RECORDING_ENABLED' => $this->isTalkRecordingEnabled ? 'yes' : '',
            'ONLYOFFICE_ENABLED' => $this->isOnlyofficeEnabled ? 'yes' : '',
            'COLLABORA_ENABLED' => $this->isCollaboraEnabled ? 'yes' : '',
            'TALK_ENABLED' => $this->isTalkEnabled ? 'yes' : '',
            'UPDATE_NEXTCLOUD_APPS' => ($this->isDailyBackupRunning() && $this->areAutomaticUpdatesEnabled()) ? 'yes' : '',
            'TIMEZONE' => $this->timezone === '' ? 'Etc/UTC' : $this->timezone,
            'COLLABORA_DICTIONARIES' => $this->collaboraDictionaries === '' ? 'de_DE en_GB en_US es_ES fr_FR it nl pt_BR pt_PT ru' : $this->collaboraDictionaries,
            'IMAGINARY_ENABLED' => $this->isImaginaryEnabled ? 'yes' : '',
            'FULLTEXTSEARCH_ENABLED' => $this->isFulltextsearchEnabled ? 'yes' : '',
            'DOCKER_SOCKET_PROXY_ENABLED' => $this->isDockerSocketProxyEnabled ? 'yes' : '',
            'NEXTCLOUD_UPLOAD_LIMIT' => $this->nextcloudUploadLimit,
            'NEXTCLOUD_MEMORY_LIMIT' => $this->nextcloudMemoryLimit,
            'NEXTCLOUD_MAX_TIME' => $this->nextcloudMaxTime,
            'BORG_RETENTION_POLICY' => $this->borgRetentionPolicy,
            'FULLTEXTSEARCH_JAVA_OPTIONS' => $this->fulltextsearchJavaOptions,
            'NEXTCLOUD_TRUSTED_CACERTS_DIR' => $this->trustedCacertsDir,
            'ADDITIONAL_DIRECTORIES_BACKUP' => $this->getAdditionalBackupDirectoriesString() !== '' ? 'yes' : '',
            'BORGBACKUP_HOST_LOCATION' => $this->borgBackupHostLocation,
            'APACHE_MAX_SIZE' => (string)($this->getApacheMaxSize()),
            'COLLABORA_SECCOMP_POLICY' => $this->getCollaboraSeccompPolicy(),
            'NEXTCLOUD_STARTUP_APPS' => $this->getNextcloudStartupApps(),
            'NEXTCLOUD_ADDITIONAL_APKS' => $this->nextcloudAdditionalApks,
            'NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS' => $this->nextcloudAdditionalPhpExtensions,
            'INSTALL_LATEST_MAJOR' => $this->installLatestMajor ? 'yes' : '',
            'REMOVE_DISABLED_APPS' => $this->nextcloudKeepDisabledApps ? '' : 'yes',
            // Allow to get local ip-address of database container which allows to talk to it even in host mode (the container that requires this needs to be started first then)
            'AIO_DATABASE_HOST' => gethostbyname('nextcloud-aio-database'),
            // Allow to get local ip-address of caddy container and add it to trusted proxies automatically
            'CADDY_IP_ADDRESS' => in_array('caddy', $this->aioCommunityContainers, true) ? gethostbyname('nextcloud-aio-caddy') : '',
            'WHITEBOARD_ENABLED' => $this->isWhiteboardEnabled ? 'yes' : '',
            'AIO_VERSION' => $this->getAioVersion(),
            default => $this->getRegisteredSecret($placeholder),
        };
    }
    
    private function booleanize(mixed $value) : bool {
        return in_array($value, [true, 'true'], true);
    }
}
