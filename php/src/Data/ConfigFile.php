<?php

namespace AIO\Data;

use JsonException;
use JsonSerializable;


function _bool(array $json, int|string $key): bool {
    return $json[$key] === true || $json[$key] === 1 || $json[$key] === 'true';
}

function _env_bool(string $envKey, array $json, int|string $key): bool {
    $envVar = getenv($envKey);
    if (is_string($envVar)) return $envVar === 'true' || $envVar === '1';
    return $json[$key] === true || $json[$key] === 1 || $json[$key] === 'true' || $json[$key] === '1';
}

/**  @throws JsonException */
function _string(array $json, int|string $key, string $default = ''): string {
    if (!isset($json[$key])) return $default;
    if (is_string($json[$key])) return $json[$key];
    throw new JsonException("Invalid JSON type for key '$key': expected 'string' got '" . gettype($json[$key]) . "'");
}

/**  @throws JsonException */
function _env_string(string $envKey, array $json, int|string $key, string $default = ''): string {
    $envVar = getenv($envKey);
    if (is_string($envVar)) return $envVar;
    return _string($json, $key, $default);
}

/**  @throws JsonException */
function _int(array $json, int|string $key, int $default = 0): int {
    if (!isset($json[$key])) return $default;
    if (is_numeric($json[$key])) return intval($json[$key]);
    throw new JsonException("Invalid JSON type for key '$key': expected 'int' got '" . gettype($json[$key]) . "'");
}

/**  @throws JsonException */
function _env_int(string $envKey, array $json, int|string $key, int $default = 0): int {
    $envVar = getenv($envKey);
    if (is_numeric($envVar)) return intval($envVar);
    return _int($json, $key, $default);
}

/**  @throws JsonException */
function _object(mixed $data): array {
    if (!isset($data)) return [];
    if (is_array($data)) return $data;
    if (is_object($data)) return (array)$data;
    throw new JsonException("Invalid JSON type: expected 'array' or 'object' got '" . gettype($data) . "'");
}


/**
 * @psalm-suppress MixedReturnTypeCoercion
 * @return array<string, string>
 * @throws JsonException
 */
function _map_str_str(array $json, int|string $key): array {
    return array_filter(_object($json[$key]), fn($k, $v) => is_string($k) && is_string($v), ARRAY_FILTER_USE_BOTH);
}


class ConfigFile implements JsonSerializable {
    //////////////////////
    /// Object Methods ///
    //////////////////////
    readonly bool $x64Platform;
    private bool $clamav, $talkRecording, $onlyoffice, $collabora;

    // Bool keys
    const string KEY_CLAMAV = 'isClamavEnabled';
    const string KEY_DOCKER_SOCKET_PROXY = 'isDockerSocketProxyEnabled';
    const string KEY_WHITEBOARD = 'isWhiteboardEnabled';
    const string KEY_IMAGINARY = 'isImaginaryEnabled';
    const string KEY_FULLTEXTSEARCH = 'isFulltextsearchEnabled';
    const string KEY_ONLYOFFICE = 'isOnlyofficeEnabled';
    const string KEY_COLLABORA = 'isCollaboraEnabled';
    const string KEY_TALK = 'isTalkEnabled';
    const string KEY_TALK_RECORDING = 'isTalkRecordingEnabled';
    const string KEY_START_BUTTON_CLICKED = 'wasStartButtonClicked';
    const string KEY_INSTALL_LATEST_MAJOR = 'shouldLatestMajorGetInstalled';
    // Readonly keys
    const string KEY_APACHE_PORT = 'apache_port';
    const string KEY_TALK_PORT = 'talk_port';
    const string KEY_NEXTCLOUD_MOUNT = 'nextcloud_mount';
    const string KEY_NEXTCLOUD_DATADIR = 'nextcloud_datadir';
    const string KEY_NEXTCLOUD_UPLOAD_LIMIT = 'nextcloud_upload_limit';
    const string KEY_NEXTCLOUD_MEMORY_LIMIT = 'nextcloud_memory_limit';
    const string KEY_NEXTCLOUD_MAX_TIME = 'nextcloud_max_time';
    const string KEY_BORG_RETENTION_POLICY = 'borg_retention_policy';
    const string KEY_DOCKER_SOCKET_PATH = 'docker_socket_path';
    const string KEY_TRUSTED_CACERTS_DIR = 'trusted_cacerts_dir';
    const string KEY_NEXTCLOUD_ADDITIONAL_APKS = 'nextcloud_additional_apks';
    const string KEY_NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS = 'nextcloud_additional_php_extensions';
    const string KEY_APACHE_IP_BINDING = 'apache_ip_binding';
    const string KEY_AIO_DISABLE_BACKUP_SECTION = 'aio_disable_backup_section';
    const string KEY_AIO_COMMUNITY_CONTAINERS = 'aio_community_containers';
    const string KEY_NEXTCLOUD_ENABLE_DRI_DEVICE = 'nextcloud_enable_dri_device';
    const string KEY_COLLABORA_SECCOMP_DISABLED = 'collabora_seccomp_disabled';
    const string KEY_NEXTCLOUD_KEEP_DISABLED_APPS = 'nextcloud_keep_disabled_apps';
    // String keys
    const string KEY_INSTANCE_RESTORE_ATTEMPT = 'instance_restore_attempt';
    const string KEY_BORG_LOCATION = 'borg_backup_host_location';
    const string KEY_BORG_PASSWORD = 'borg_restore_password';
    const string KEY_BACKUP_MODE = 'backup-mode';
    const string KEY_SELECTED_RESTORE_TIME = 'selected-restore-time';
    // Other keys
    const string KEY_PASSWORD = 'password';
    const string KEY_TOKEN = 'AIO_TOKEN';
    const string KEY_DOMAIN = 'domain';
    const string KEY_AIO_URL = 'AIO_URL';
    const string KEY_TIMEZONE = 'timezone';
    const string KEY_COLLABORA_DICTIONARIES = 'collabora_dictionaries';
    const string KEY_SECRETS = 'secrets';


    private function __construct(
        // Bool data
        bool           $clamavEnabled,
        public bool    $dockerSocketProxyEnabled,
        public bool    $whiteboardEnabled,
        public bool    $imaginaryEnabled,
        public bool    $fulltextsearchEnabled,
        bool           $onlyofficeEnabled,
        bool           $collaboraEnabled,
        public bool    $talkEnabled,
        bool           $talkRecordingEnabled,
        public bool    $wasStartButtonClicked,
        // Readonly data
        public int     $apachePort,
        public int     $talkPort,
        public string  $nextcloudMount,
        public string  $nextcloudDatadir,
        public string  $nextcloudUploadLimit,
        public string  $nextcloudMemoryLimit,
        public string  $nextcloudMaxTime,
        public string  $borgRetentionPolicy,
        public string  $dockerSocketPath,
        public string  $trustedCacertsDir,
        public string  $nextcloudAdditionalApks,
        public string  $nextcloudAdditionalPhpExtensions,
        public string  $apacheIpBinding,
        public bool    $aioDisableBackupSection,
        public bool    $nextcloudEnableDriDevice,
        public bool    $nextcloudKeepDisabledApps,
        private bool   $collaboraSeccompDisabled,
        /** @var list<string> $aioCommunityContainers */
        public array   $aioCommunityContainers,

        // Backup data
        public int     $instanceRestoreAttempt,
        private string $borgLocation,
        private string $borgPassword,
        private string $backupMode,
        private string $selectedRestoreTime,
        // Other data
        public int     $installLatestMajor,
        private string $password,
        private string $token,
        private string $domain,
        public string  $aioUrl,
        private string $timezone,
        private string $collaboraDictionaries,
        /** @var array<string, string> $secrets */
        private array  $secrets = [],
    ) {
        $this->x64Platform = php_uname('m') === 'x86_64';
        $this->clamav = $clamavEnabled && $this->x64Platform;
        $this->talkRecording = $talkRecordingEnabled && $this->talkEnabled;
        $this->collabora = $collaboraEnabled;
        $this->onlyoffice = $onlyofficeEnabled && !$this->collabora;
    }

    /**  @throws InvalidSettingConfigurationException */
    static function parse(mixed $data): self {
        try {
            $json = _object($data);
            return new self(
            // Bool data
                clamavEnabled: _bool($json, self::KEY_CLAMAV),
                dockerSocketProxyEnabled: _bool($json, self::KEY_DOCKER_SOCKET_PROXY),
                whiteboardEnabled: _bool($json, self::KEY_WHITEBOARD),
                imaginaryEnabled: _bool($json, self::KEY_IMAGINARY),
                fulltextsearchEnabled: _bool($json, self::KEY_FULLTEXTSEARCH),
                onlyofficeEnabled: _bool($json, self::KEY_ONLYOFFICE),
                collaboraEnabled: _bool($json, self::KEY_COLLABORA),
                talkEnabled: _bool($json, self::KEY_TALK),
                talkRecordingEnabled: _bool($json, self::KEY_TALK_RECORDING),
                wasStartButtonClicked: _bool($json, self::KEY_START_BUTTON_CLICKED),
                // Readonly data
                apachePort: _env_int('APACHE_PORT', $json, self::KEY_APACHE_PORT, 443),
                talkPort: _env_int('TALK_PORT', $json, self::KEY_TALK_PORT, 3478),
                nextcloudMount: _env_string('NEXTCLOUD_MOUNT', $json, self::KEY_NEXTCLOUD_MOUNT),
                nextcloudDatadir: _env_string('NEXTCLOUD_DATADIR', $json, self::KEY_NEXTCLOUD_DATADIR, 'nextcloud_aio_nextcloud_data'),
                nextcloudUploadLimit: _env_string('NEXTCLOUD_UPLOAD_LIMIT', $json, self::KEY_NEXTCLOUD_UPLOAD_LIMIT, '10G'),
                nextcloudMemoryLimit: _env_string('NEXTCLOUD_MEMORY_LIMIT', $json, self::KEY_NEXTCLOUD_MEMORY_LIMIT, '512M'),
                nextcloudMaxTime: _env_string('NEXTCLOUD_MAX_TIME', $json, self::KEY_NEXTCLOUD_MAX_TIME, '3600'),
                borgRetentionPolicy: _env_string('BORG_RETENTION_POLICY', $json, self::KEY_BORG_RETENTION_POLICY, '--keep-within=7d --keep-weekly=4 --keep-monthly=6'),
                dockerSocketPath: _env_string('DOCKER_SOCKET_PATH', $json, self::KEY_DOCKER_SOCKET_PATH, '/var/run/docker.sock'),
                trustedCacertsDir: _env_string('TRUSTED_CACERTS_DIR', $json, self::KEY_TRUSTED_CACERTS_DIR),
                nextcloudAdditionalApks: _env_string('NEXTCLOUD_ADDITIONAL_APKS', $json, self::KEY_NEXTCLOUD_ADDITIONAL_APKS, 'imagemagick'),
                nextcloudAdditionalPhpExtensions: _env_string('NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS', $json, self::KEY_NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS, 'imagick'),
                apacheIpBinding: _env_string('APACHE_IP_BINDING', $json, self::KEY_APACHE_IP_BINDING),
                aioDisableBackupSection: _env_bool('AIO_DISABLE_BACKUP_SECTION', $json, self::KEY_AIO_DISABLE_BACKUP_SECTION),
                nextcloudEnableDriDevice: _env_bool('NEXTCLOUD_ENABLE_DRI_DEVICE', $json, self::KEY_NEXTCLOUD_ENABLE_DRI_DEVICE),
                nextcloudKeepDisabledApps: _env_bool('NEXTCLOUD_KEEP_DISABLED_APPS', $json, self::KEY_NEXTCLOUD_KEEP_DISABLED_APPS),
                collaboraSeccompDisabled: _env_bool('COLLABORA_SECCOMP_DISABLED', $json, self::KEY_COLLABORA_SECCOMP_DISABLED),
                aioCommunityContainers: _env_string('AIO_COMMUNITY_CONTAINERS', $json, self::KEY_AIO_COMMUNITY_CONTAINERS),
                // Backup data
                instanceRestoreAttempt: _int($json, self::KEY_INSTANCE_RESTORE_ATTEMPT),
                borgLocation: _string($json, self::KEY_BORG_LOCATION),
                borgPassword: _string($json, self::KEY_BORG_PASSWORD),
                backupMode: _string($json, self::KEY_BACKUP_MODE),
                selectedRestoreTime: _string($json, self::KEY_SELECTED_RESTORE_TIME),
                // Other data
                installLatestMajor: _int($json, self::KEY_INSTALL_LATEST_MAJOR),
                password: _string($json, self::KEY_PASSWORD),
                token: _string($json, self::KEY_TOKEN),
                domain: _string($json, self::KEY_DOMAIN),
                aioUrl: _string($json, self::KEY_AIO_URL),
                timezone: _string($json, self::KEY_TIMEZONE),
                collaboraDictionaries: _string($json, self::KEY_COLLABORA_DICTIONARIES),
                secrets: _map_str_str($json, self::KEY_SECRETS),
            );

        } catch (JsonException $e) {
            throw new InvalidSettingConfigurationException('Failed to parse JSON data', previous: $e);
        }
    }

    static function blank(string $password): self {
        return new self(
        // Bool data
            clamavEnabled: false,
            dockerSocketProxyEnabled: false,
            whiteboardEnabled: false,
            imaginaryEnabled: false,
            fulltextsearchEnabled: false,
            onlyofficeEnabled: false,
            collaboraEnabled: false,
            talkEnabled: false,
            talkRecordingEnabled: false,
            wasStartButtonClicked: false,
            // Readonly data
            apachePort: 0,
            talkPort: 0,
            nextcloudMount: '',
            nextcloudDatadir: '',
            nextcloudUploadLimit: '',
            nextcloudMemoryLimit: '',
            nextcloudMaxTime: '',
            borgRetentionPolicy: '',
            dockerSocketPath: '',
            trustedCacertsDir: '',
            nextcloudAdditionalApks: '',
            nextcloudAdditionalPhpExtensions: '',
            apacheIpBinding: '',
            aioDisableBackupSection: false,
            nextcloudEnableDriDevice: false,
            nextcloudKeepDisabledApps: false,
            collaboraSeccompDisabled: false,
            aioCommunityContainers: [],
            // Backup data
            instanceRestoreAttempt: 0,
            borgLocation: '',
            borgPassword: '',
            backupMode: '',
            selectedRestoreTime: '',
            // Other data
            installLatestMajor: 0,
            password: $password,
            token: '',
            domain: '',
            aioUrl: '',
            timezone: '',
            collaboraDictionaries: '',
            secrets: [],
        );
    }

    function jsonSerialize(): array {
        $json = [];
        // Bool data
        if ($this->clamav) $json[self::KEY_CLAMAV] = true;
        if ($this->dockerSocketProxyEnabled) $json[self::KEY_DOCKER_SOCKET_PROXY] = true;
        if ($this->whiteboardEnabled) $json[self::KEY_WHITEBOARD] = true;
        if ($this->imaginaryEnabled) $json[self::KEY_IMAGINARY] = true;
        if ($this->fulltextsearchEnabled) $json[self::KEY_FULLTEXTSEARCH] = true;
        if ($this->onlyoffice) $json[self::KEY_ONLYOFFICE] = true;
        if ($this->collabora) $json[self::KEY_COLLABORA] = true;
        if ($this->talkEnabled) $json[self::KEY_TALK] = true;
        if ($this->talkRecording) $json[self::KEY_TALK_RECORDING] = true;
        if ($this->wasStartButtonClicked) $json[self::KEY_START_BUTTON_CLICKED] = true;
        // Backup data
        if (!empty($this->instanceRestoreAttempt)) $json[self::KEY_INSTANCE_RESTORE_ATTEMPT] = $this->instanceRestoreAttempt;
        if (!empty($this->borgLocation)) $json[self::KEY_BORG_LOCATION] = $this->borgLocation;
        if (!empty($this->borgPassword)) $json[self::KEY_BORG_PASSWORD] = $this->borgPassword;
        if (!empty($this->backupMode)) $json[self::KEY_BACKUP_MODE] = $this->backupMode;
        if (!empty($this->selectedRestoreTime)) $json[self::KEY_SELECTED_RESTORE_TIME] = $this->selectedRestoreTime;
        // Other data
        if (!empty($this->password)) $json[self::KEY_PASSWORD] = $this->password;
        if (!empty($this->token)) $json[self::KEY_TOKEN] = $this->token;
        if (!empty($this->domain)) $json[self::KEY_DOMAIN] = $this->domain;
        if (!empty($this->aioUrl)) $json[self::KEY_AIO_URL] = $this->aioUrl;
        if (!empty($this->timezone)) $json[self::KEY_TIMEZONE] = $this->timezone;
        if (!empty($this->collaboraDictionaries)) $json[self::KEY_COLLABORA_DICTIONARIES] = $this->collaboraDictionaries;
        if (!empty($this->secrets)) $json[self::KEY_SECRETS] = $this->secrets;

        return $json;
    }

    function overwrite(string $key, string $value): void {
        switch ($key) {
            case self::KEY_APACHE_IP_BINDING:
                $this->apacheIpBinding = $value;
                break;
            case self::KEY_APACHE_PORT:
                if (is_numeric($value)) $this->apachePort = intval($value);
                break;
            case self::KEY_NEXTCLOUD_MEMORY_LIMIT:
                $this->nextcloudMemoryLimit = $value;
                break;
            default:
        }

    }
    /////////////////////////
    /// Bool data Methods ///
    /////////////////////////
    function isClamavEnabled(): bool {
        return $this->clamav;
    }

    function enableClamav(bool $clamav): void {
        if ($this->x64Platform) $this->clamav = $clamav;
    }

    function isCollaboraEnabled(): bool {
        return $this->collabora;
    }

    function enableCollabora(bool $collabora): void {
        $this->collabora = $collabora;
        $this->onlyoffice = false;
    }

    function isOnlyofficeEnabled(): bool {
        return $this->onlyoffice;
    }

    function enableOnlyoffice(bool $onlyoffice): void {
        if ($this->collabora)
            $this->onlyoffice = $onlyoffice;
    }

    function isTalkRecordingEnabled(): bool {
        return $this->talkRecording;
    }

    function enableTalkRecording(bool $talkRecording): void {
        if ($this->talkEnabled)
            $this->talkRecording = $talkRecording;
    }

    /////////////////////////////
    /// Readonly data Methods ///
    /////////////////////////////
    function getCollaboraSeccompPolicy(): string {
        return $this->collaboraSeccompDisabled
            ? '--o:security.seccomp=false'
            : '--o:security.seccomp=true';
    }

    function getApacheMaxSize(): int {
        return intval(rtrim($this->nextcloudUploadLimit, 'G')) * 1024 * 1024 * 1024;
    }

    ///////////////////////////
    /// Backup data Methods ///
    ///////////////////////////
    function getBorgLocation(): string {
        return $this->borgLocation;
    }

    /** @throws InvalidSettingConfigurationException */
    function setBorgLocation(string $location): void {
        if ($location !== 'nextcloud_aio_backupdir' && (!str_starts_with($location, '/') || str_ends_with($location, '/')))
            throw new InvalidSettingConfigurationException("The path must start with '/', and must not end with '/'!");
        $this->borgLocation = $location;
    }

    function deleteBorgLocation(): void {
        $this->borgLocation = '';
    }

    function getBorgPassword(): string {
        return $this->borgPassword;
    }

    /**  @throws InvalidSettingConfigurationException */
    function setBorgRestoreLocationAndPassword(string $location, string $password): void {
        if ($password === '')
            throw new InvalidSettingConfigurationException("Please enter the password!");
        $this->setBorgLocation($location);
        $this->password = $password;
        $this->instanceRestoreAttempt = 1;
    }

    function getBackupMode(): string {
        return $this->backupMode;
    }

    function setBackupMode(string $backupMode): void {
        $this->backupMode = $backupMode;
    }

    function getSelectedRestoreTime(): string {
        return $this->selectedRestoreTime;
    }

    function setSelectedRestoreTime(string $selectedRestoreTime): void {
        $this->selectedRestoreTime = $selectedRestoreTime;
    }

    /////////////////////////
    /// Other data Methods //
    /////////////////////////
    function getPassword(): string {
        return $this->password;
    }

    /** @throws InvalidSettingConfigurationException */
    function changeMasterPassword(string $currentPassword, string $newPassword): void {
        if ($currentPassword === '')
            throw new InvalidSettingConfigurationException("Please enter your current password.");
        if ($currentPassword !== $this->password)
            throw new InvalidSettingConfigurationException("The entered current password is not correct.");
        if ($newPassword === '')
            throw new InvalidSettingConfigurationException("Please enter a new password.");
        if (strlen($newPassword) < 24)
            throw new InvalidSettingConfigurationException("New passwords must be >= 24 digits.");
        if (!preg_match("#^[a-zA-Z0-9 ]+$#", $newPassword))
            throw new InvalidSettingConfigurationException('Not allowed characters in the new password.');

        $this->password = $newPassword;
    }

    function getToken(): string {
        return $this->token;
    }

    function setToken(string $token): void {
        $this->token = $token;
    }

    function getDomain(): string {
        return $this->domain;
    }

    /* @throws InvalidSettingConfigurationException */
    function setDomain(string $domain): void {
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
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            throw new InvalidSettingConfigurationException("Please enter a domain and not an IP-address!");
        }
// TODO
//
//        // Skip domain validation if opted in to do so
//        if (!$this->shouldDomainValidationBeSkipped()) {
//
//            $dnsRecordIP = gethostbyname($domain);
//            if ($dnsRecordIP === $domain) {
//                $dnsRecordIP = '';
//            }
//
//            if (empty($dnsRecordIP)) {
//                $record = dns_get_record($domain, DNS_AAAA);
//                if (isset($record[0]['ipv6']) && !empty($record[0]['ipv6'])) {
//                    $dnsRecordIP = $record[0]['ipv6'];
//                }
//            }
//
//            // Validate IP
//            if (!filter_var($dnsRecordIP, FILTER_VALIDATE_IP)) {
//                throw new InvalidSettingConfigurationException("DNS config is not set for this domain or the domain is not a valid domain! (It was found to be set to '" . $dnsRecordIP . "')");
//            }
//
//            // Get the apache port
//            $port = $this->GetApachePort();
//
//            if (!filter_var($dnsRecordIP, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
//                if ($port === '443') {
//                    throw new InvalidSettingConfigurationException("It seems like the ip-address of the domain is set to an internal or reserved ip-address. This is not supported. (It was found to be set to '" . $dnsRecordIP . "'). Please set it to a public ip-address so that the domain validation can work!");
//                } else {
//                    error_log("It seems like the ip-address of " . $domain . " is set to an internal or reserved ip-address. (It was found to be set to '" . $dnsRecordIP . "')");
//                }
//            }
//
//            // Check if port 443 is open
//            $connection = @fsockopen($domain, 443, $errno, $errstr, 10);
//            if ($connection) {
//                fclose($connection);
//            } else {
//                throw new InvalidSettingConfigurationException("The domain is not reachable on Port 443 from within this container. Have you opened port 443/tcp in your router/firewall? If yes is the problem most likely that the router or firewall forbids local access to your domain. You can work around that by setting up a local DNS-server.");
//            }
//
//            // Get Instance ID
//            $instanceID = $this->GetAndGenerateSecret('INSTANCE_ID');
//
//            // set protocol
//            if ($port !== '443') {
//                $protocol = 'https://';
//            } else {
//                $protocol = 'http://';
//            }
//
//            // Check if response is correct
//            $ch = curl_init();
//            $testUrl = $protocol . $domain . ':443';
//            curl_setopt($ch, CURLOPT_URL, $testUrl);
//            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
//            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
//            $response = (string)curl_exec($ch);
//            # Get rid of trailing \n
//            $response = str_replace("\n", "", $response);
//
//            if ($response !== $instanceID) {
//                error_log('The response of the connection attempt to "' . $testUrl . '" was: ' . $response);
//                error_log('Expected was: ' . $instanceID);
//                error_log('The error message was: ' . curl_error($ch));
//                $notice = "Domain does not point to this server or the reverse proxy is not configured correctly. See the mastercontainer logs for more details. ('sudo docker logs -f nextcloud-aio-mastercontainer')";
//                if ($port === '443') {
//                    $notice .= " If you should be using Cloudflare, make sure to disable the Cloudflare Proxy feature as it might block the domain validation. Same for any other firewall or service that blocks unencrypted access on port 443.";
//                } else {
//                    error_log('Please follow https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md#6-how-to-debug-things in order to debug things!');
//                }
//                throw new InvalidSettingConfigurationException($notice);
//            }
//        }

        // Write domain
        $this->domain = $domain;
        // Reset the borg restore password when setting the domain
        $this->borgPassword = '';
    }

    function getBaseDN(): string {
        $domain = $this->getDomain();
        if ($domain === "") {
            return "";
        }
        return 'dc=' . implode(',dc=', explode('.', $domain));
    }

    function getTimezone(): string {
        return $this->timezone;
    }

    /* @throws InvalidSettingConfigurationException */
    function setTimezone(string $timezone): void {
        if ($timezone === "")
            throw new InvalidSettingConfigurationException("The timezone must not be empty!");
        if (!preg_match("#^[a-zA-Z0-9_\-/+]+$#", $timezone))
            throw new InvalidSettingConfigurationException("The entered timezone does not seem to be a valid timezone!");
        $this->timezone = $timezone;
    }

    function deleteTimezone(): void {
        $this->timezone = '';
    }

    function getCollaboraDictionaries(): string {
        return $this->collaboraDictionaries;
    }

    /**  @throws InvalidSettingConfigurationException */
    function setCollaboraDictionaries(string $collaboraDictionaries): void {
        if ($collaboraDictionaries === "")
            throw new InvalidSettingConfigurationException("The dictionaries must not be empty!");
        if (!preg_match("#^[a-zA-Z_ ]+$#", $collaboraDictionaries))
            throw new InvalidSettingConfigurationException("The entered dictionaries do not seem to be a valid!");
        $this->collaboraDictionaries = $collaboraDictionaries;
    }

    function deleteCollaboraDictionaries(): void {
        $this->collaboraDictionaries = '';
    }

    function getSecret(string $key): ?string {
        return $this->secrets[$key] ?? null;
    }

    function setSecret(string $key, string $data): void {
        $this->secrets[$key] = $data;
    }
}
