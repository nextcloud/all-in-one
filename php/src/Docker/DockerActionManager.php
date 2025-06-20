<?php

namespace AIO\Docker;

use AIO\Container\Container;
use AIO\Container\ContainerState;
use AIO\Container\VersionState;
use AIO\ContainerDefinitionFetcher;
use AIO\Data\ConfigurationManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use http\Env\Response;

readonly class DockerActionManager {
    private const string API_VERSION = 'v1.41';
    private Client $guzzleClient;

    public function __construct(
        private ConfigurationManager           $configurationManager,
        private ContainerDefinitionFetcher     $containerDefinitionFetcher,
        private DockerHubManager               $dockerHubManager,
        private GitHubContainerRegistryManager $gitHubContainerRegistryManager
    ) {
        $this->guzzleClient = new Client(['curl' => [CURLOPT_UNIX_SOCKET_PATH => '/var/run/docker.sock']]);
    }

    private function BuildApiUrl(string $url): string {
        return sprintf('http://127.0.0.1/%s/%s', self::API_VERSION, $url);
    }

    private function BuildImageName(Container $container): string {
        $tag = $container->GetImageTag();
        if ($tag === '%AIO_CHANNEL%') {
            $tag = $this->GetCurrentChannel();
        }
        return $container->GetContainerName() . ':' . $tag;
    }

    public function GetContainerRunningState(Container $container): ContainerState {
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', urlencode($container->GetIdentifier())));
        try {
            $response = $this->guzzleClient->get($url);
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return ContainerState::ImageDoesNotExist;
            }
            throw $e;
        }

        $responseBody = json_decode((string)$response->getBody(), true);

        if ($responseBody['State']['Running'] === true) {
            return ContainerState::Running;
        } else {
            return ContainerState::Stopped;
        }
    }

    public function GetContainerRestartingState(Container $container): ContainerState {
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', urlencode($container->GetIdentifier())));
        try {
            $response = $this->guzzleClient->get($url);
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return ContainerState::ImageDoesNotExist;
            }
            throw $e;
        }

        $responseBody = json_decode((string)$response->getBody(), true);

        if ($responseBody['State']['Restarting'] === true) {
            return ContainerState::Restarting;
        } else {
            return ContainerState::NotRestarting;
        }
    }

    public function GetContainerUpdateState(Container $container): VersionState {
        $tag = $container->GetImageTag();
        if ($tag === '%AIO_CHANNEL%') {
            $tag = $this->GetCurrentChannel();
        }

        $runningDigests = $this->GetRepoDigestsOfContainer($container->GetIdentifier());
        if ($runningDigests === null) {
            return VersionState::Different;
        }
        $remoteDigest = $this->GetLatestDigestOfTag($container->GetContainerName(), $tag);
        if ($remoteDigest === null) {
            return VersionState::Equal;
        }

        foreach ($runningDigests as $runningDigest) {
            if ($runningDigest === $remoteDigest) {
                return VersionState::Equal;
            }
        }
        return VersionState::Different;
    }

    public function GetContainerStartingState(Container $container): ContainerState {
        $runningState = $this->GetContainerRunningState($container);
        if ($runningState === ContainerState::Stopped || $runningState === ContainerState::ImageDoesNotExist) {
            return $runningState;
        }

        $containerName = $container->GetIdentifier();
        $internalPort = $container->GetInternalPort();
        if ($internalPort === '%APACHE_PORT%') {
            $internalPort = $this->configurationManager->GetApachePort();
        } elseif ($internalPort === '%TALK_PORT%') {
            $internalPort = $this->configurationManager->GetTalkPort();
        }

        if ($internalPort !== "" && $internalPort !== 'host') {
            $connection = @fsockopen($containerName, (int)$internalPort, $errno, $errstr, 0.2);
            if ($connection) {
                fclose($connection);
                return ContainerState::Running;
            } else {
                return ContainerState::Starting;
            }
        } else {
            return ContainerState::Running;
        }
    }

    public function DeleteContainer(Container $container): void {
        $url = $this->BuildApiUrl(sprintf('containers/%s?v=true', urlencode($container->GetIdentifier())));
        try {
            $this->guzzleClient->delete($url);
        } catch (RequestException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    public function GetLogs(string $id): string {
        $url = $this->BuildApiUrl(
            sprintf(
                'containers/%s/logs?stdout=true&stderr=true&timestamps=true',
                urlencode($id)
            ));
        $responseBody = (string)$this->guzzleClient->get($url)->getBody();

        $response = "";
        $separator = "\r\n";
        $line = strtok($responseBody, $separator);
        $response = substr($line, 8) . $separator;

        while ($line !== false) {
            $line = strtok($separator);
            $response .= substr($line, 8) . $separator;
        }

        return $response;
    }

    public function StartContainer(Container $container): void {
        $url = $this->BuildApiUrl(sprintf('containers/%s/start', urlencode($container->GetIdentifier())));
        try {
            $this->guzzleClient->post($url);
        } catch (RequestException $e) {
            throw new \Exception("Could not start container " . $container->GetIdentifier() . ": " . $e->getResponse()?->getBody()->getContents());
        }
    }

    public function CreateVolumes(Container $container): void {
        $url = $this->BuildApiUrl('volumes/create');
        foreach ($container->GetVolumes()->GetVolumes() as $volume) {
            $forbiddenChars = [
                '/',
            ];

            if ($volume->name === 'nextcloud_aio_nextcloud_datadir' || $volume->name === 'nextcloud_aio_backupdir') {
                return;
            }

            $firstChar = substr($volume->name, 0, 1);
            if (!in_array($firstChar, $forbiddenChars)) {
                $this->guzzleClient->request(
                    'POST',
                    $url,
                    [
                        'json' => [
                            'name' => $volume->name,
                        ],
                    ]
                );
            }
        }
    }

    public function CreateContainer(Container $container): void {
        $volumes = [];
        foreach ($container->GetVolumes()->GetVolumes() as $volume) {
            // // NEXTCLOUD_MOUNT gets added via bind-mount later on
            // if ($container->GetIdentifier() === 'nextcloud-aio-nextcloud') {
            //     if ($volume->name === $this->configurationManager->GetNextcloudMount()) {
            //         continue;
            //     }
            // }

            $volumeEntry = $volume->name . ':' . $volume->mountPoint;
            if ($volume->isWritable) {
                $volumeEntry = $volumeEntry . ':' . 'rw';
            } else {
                $volumeEntry = $volumeEntry . ':' . 'ro';
            }

            $volumes[] = $volumeEntry;
        }

        $requestBody = [
            'Image' => $this->BuildImageName($container),
        ];

        if (count($volumes) > 0) {
            $requestBody['HostConfig']['Binds'] = $volumes;
        }

        foreach ($container->GetSecrets() as $secret) {
            $this->configurationManager->GetAndGenerateSecret($secret);
        }

        $aioVariables = $container->GetAioVariables()->GetVariables();
        foreach ($aioVariables as $variable) {
            $config = $this->configurationManager->GetConfig();
            $variableArray = explode('=', $variable);
            $config[$variableArray[0]] = $variableArray[1];
            $this->configurationManager->WriteConfig($config);
            sleep(1);
        }

        $envs = $container->GetEnvironmentVariables()->GetVariables();
        // Special thing for the nextcloud container
        if ($container->GetIdentifier() === 'nextcloud-aio-nextcloud') {
            $envs[] = $this->GetAllNextcloudExecCommands();
        }
        foreach ($envs as $key => $env) {
            $envs[$key] = $this->replaceEnvPlaceholders($env);
        }

        if (count($envs) > 0) {
            $requestBody['Env'] = $envs;
        }

        $requestBody['HostConfig']['RestartPolicy']['Name'] = $container->GetRestartPolicy();

        $requestBody['HostConfig']['ReadonlyRootfs'] = $container->GetReadOnlySetting();

        $exposedPorts = [];
        if ($container->GetInternalPort() !== 'host') {
            foreach ($container->GetPorts()->GetPorts() as $value) {
                $port = $value->port;
                $protocol = $value->protocol;
                if ($port === '%APACHE_PORT%') {
                    $port = $this->configurationManager->GetApachePort();
                    // Do not expose udp if AIO is in reverse proxy mode
                    if ($port !== '443' && $protocol === 'udp') {
                        continue;
                    }
                } else if ($port === '%TALK_PORT%') {
                    $port = $this->configurationManager->GetTalkPort();
                }
                $portWithProtocol = $port . '/' . $protocol;
                $exposedPorts[$portWithProtocol] = null;
            }
            $requestBody['HostConfig']['NetworkMode'] = 'nextcloud-aio';
        } else {
            $requestBody['HostConfig']['NetworkMode'] = 'host';
        }

        if (count($exposedPorts) > 0) {
            $requestBody['ExposedPorts'] = $exposedPorts;
            foreach ($container->GetPorts()->GetPorts() as $value) {
                $port = $value->port;
                $protocol = $value->protocol;
                if ($port === '%APACHE_PORT%') {
                    $port = $this->configurationManager->GetApachePort();
                    // Do not expose udp if AIO is in reverse proxy mode
                    if ($port !== '443' && $protocol === 'udp') {
                        continue;
                    }
                } else if ($port === '%TALK_PORT%') {
                    $port = $this->configurationManager->GetTalkPort();
                }
                $ipBinding = $value->ipBinding;
                if ($ipBinding === '%APACHE_IP_BINDING%') {
                    $ipBinding = $this->configurationManager->GetApacheIPBinding();
                    // Do not expose if AIO is in internal network mode
                    if ($ipBinding === '@INTERNAL') {
                        continue;
                    }
                }
                $portWithProtocol = $port . '/' . $protocol;
                $requestBody['HostConfig']['PortBindings'][$portWithProtocol] = [
                    [
                        'HostPort' => $port,
                        'HostIp' => $ipBinding,
                    ]
                ];
            }
        }

        $devices = [];
        foreach ($container->GetDevices() as $device) {
            if ($device === '/dev/dri' && !$this->configurationManager->isDriDeviceEnabled()) {
                continue;
            }
            $devices[] = ["PathOnHost" => $device, "PathInContainer" => $device, "CgroupPermissions" => "rwm"];
        }

        if (count($devices) > 0) {
            $requestBody['HostConfig']['Devices'] = $devices;
        }

        if ($container->isNvidiaGpuEnabled() && $this->configurationManager->isNvidiaGpuEnabled()) {
            $requestBody['HostConfig']['Runtime'] = 'nvidia';
            $requestBody['HostConfig']['DeviceRequests'] = [
                [
                    "Driver" => "nvidia",
                    "Count" => 1,
                    "Capabilities" => [["gpu"]],
                ]
            ];
        }

        $shmSize = $container->GetShmSize();
        if ($shmSize > 0) {
            $requestBody['HostConfig']['ShmSize'] = $shmSize;
        }

        $tmpfs = [];
        foreach ($container->GetTmpfs() as $tmp) {
            $mode = "";
            if (str_contains($tmp, ':')) {
                $mode = explode(':', $tmp)[1];
                $tmp = explode(':', $tmp)[0];
            }
            $tmpfs[$tmp] = $mode;
        }
        if (count($tmpfs) > 0) {
            $requestBody['HostConfig']['Tmpfs'] = $tmpfs;
        }

        $requestBody['HostConfig']['Init'] = $container->GetInit();

        $capAdds = $container->GetCapAdds();
        if (count($capAdds) > 0) {
            $requestBody['HostConfig']['CapAdd'] = $capAdds;
        }

        // Disable arp spoofing
        if (!in_array('NET_RAW', $capAdds, true)) {
            $requestBody['HostConfig']['CapDrop'] = ['NET_RAW'];
        }

        // Disable SELinux for AIO containers so that it does not break them
        $requestBody['HostConfig']['SecurityOpt'] = ["label:disable"];
        if ($container->isApparmorUnconfined()) {
            $requestBody['HostConfig']['SecurityOpt'] = ["apparmor:unconfined", "label:disable"];
        }

        $mounts = [];

        // Special things for the backup container which should not be exposed in the containers.json
        if (str_starts_with($container->GetIdentifier(), 'nextcloud-aio-borgbackup')) {
            // Additional backup directories
            foreach ($this->getAllBackupVolumes() as $additionalBackupVolumes) {
                if ($additionalBackupVolumes !== '') {
                    $mounts[] = ["Type" => "volume", "Source" => $additionalBackupVolumes, "Target" => "/nextcloud_aio_volumes/" . $additionalBackupVolumes, "ReadOnly" => false];
                }
            }

            // Make volumes read only in case of borgbackup container. The viewer makes them writeable
            $isReadOnly = $container->GetIdentifier() === 'nextcloud-aio-borgbackup';

            foreach ($this->configurationManager->GetAdditionalBackupDirectoriesArray() as $additionalBackupDirectories) {
                if ($additionalBackupDirectories !== '') {
                    if (!str_starts_with($additionalBackupDirectories, '/')) {
                        $mounts[] = ["Type" => "volume", "Source" => $additionalBackupDirectories, "Target" => "/docker_volumes/" . $additionalBackupDirectories, "ReadOnly" => $isReadOnly];
                    } else {
                        $mounts[] = ["Type" => "bind", "Source" => $additionalBackupDirectories, "Target" => "/host_mounts" . $additionalBackupDirectories, "ReadOnly" => $isReadOnly, "BindOptions" => ["NonRecursive" => true]];
                    }
                }
            }
            // Special things for the talk container which should not be exposed in the containers.json
        } elseif ($container->GetIdentifier() === 'nextcloud-aio-talk') {
            // This is needed due to a bug in libwebsockets which cannot handle unlimited ulimits
            $requestBody['HostConfig']['Ulimits'] = [["Name" => "nofile", "Hard" => 200000, "Soft" => 200000]];
            // // Special things for the nextcloud container which should not be exposed in the containers.json
            // } elseif ($container->GetIdentifier() === 'nextcloud-aio-nextcloud') {
            //     foreach ($container->GetVolumes()->GetVolumes() as $volume) {
            //         if ($volume->name !== $this->configurationManager->GetNextcloudMount()) {
            //             continue;
            //         }
            //         $mounts[] = ["Type" => "bind", "Source" => $volume->name, "Target" => $volume->mountPoint, "ReadOnly" => !$volume->isWritable, "BindOptions" => [ "Propagation" => "rshared"]];
            //     }
            // Special things for the caddy community container
        } elseif ($container->GetIdentifier() === 'nextcloud-aio-caddy') {
            $requestBody['HostConfig']['ExtraHosts'] = ['host.docker.internal:host-gateway'];
            // Special things for the collabora container which should not be exposed in the containers.json
        } elseif ($container->GetIdentifier() === 'nextcloud-aio-collabora') {
            if ($this->configurationManager->GetAdditionalCollaboraOptions() !== '') {
                $requestBody['Cmd'] = [$this->configurationManager->GetAdditionalCollaboraOptions()];
            }
        }

        if (count($mounts) > 0) {
            $requestBody['HostConfig']['Mounts'] = $mounts;
        }

        $url = $this->BuildApiUrl('containers/create?name=' . $container->GetIdentifier());
        try {
            $this->guzzleClient->request(
                'POST',
                $url,
                [
                    'json' => $requestBody
                ]
            );
        } catch (RequestException $e) {
            throw new \Exception("Could not create container " . $container->GetIdentifier() . ": " . $e->getResponse()?->getBody()->getContents());
        }

    }

    public function isRegistryReachable(Container $container): bool {
        $tag = $container->GetImageTag();
        if ($tag === '%AIO_CHANNEL%') {
            $tag = $this->GetCurrentChannel();
        }

        $remoteDigest = $this->GetLatestDigestOfTag($container->GetContainerName(), $tag);

        if ($remoteDigest === null) {
            return false;
        } else {
            return true;
        }
    }

    public function PullImage(Container $container): void {
        $imageName = $this->BuildImageName($container);
        $encodedImageName = urlencode($imageName);
        $url = $this->BuildApiUrl(sprintf('images/create?fromImage=%s', $encodedImageName));
        $imageIsThere = true;
        try {
            $imageUrl = $this->BuildApiUrl(sprintf('images/%s/json', $encodedImageName));
            $this->guzzleClient->get($imageUrl)->getBody()->getContents();
        } catch (\Throwable $e) {
            $imageIsThere = false;
        }
        try {
            $this->guzzleClient->post($url);
        } catch (RequestException $e) {
            $message = "Could not pull image " . $imageName . ": " . $e->getResponse()?->getBody()->getContents();
            if ($imageIsThere === false) {
                throw new \Exception($message);
            } else {
                error_log($message);
            }
        }
    }

    // Replaces placeholders in $envValue with their values.
    // E.g. "%NC_DOMAIN%:%APACHE_PORT" becomes "my.nextcloud.com:11000"
    private function replaceEnvPlaceholders($envValue) {
        // $pattern breaks down as:
        // % - matches a literal percent sign
        // ([^%]+) - capture group that matches one or more characters that are NOT percent signs
        // % - matches the closing percent sign
        //
        // Assumes literal percent signs are always matched and there is no
        // escaping.
        $pattern = '/%([^%]+)%/';
        $matchCount = preg_match_all($pattern, $envValue, $matches);
        if ($matchCount > 0) {
            $placeholders = $matches[0]; // ["%PLACEHOLDER1%", "%PLACEHOLDER2%", ...]
            $placeholderNames = $matches[1]; // ["PLACEHOLDER1", "PLACEHOLDER2", ...]
            $placeholderToPattern = fn($placeholder) => '/' . $placeholder . '/';
            $placeholderPatterns = array_map($placeholderToPattern, $placeholders); // ["/%PLACEHOLDER1%/", ...]
            $placeholderValues = array_map([$this, 'getPlaceholderValue'], $placeholderNames); // ["val1", "val2"]
            $result = preg_replace($placeholderPatterns, $placeholderValues, $envValue);
            return $result;
        }
        return $envValue;
    }

    private function getPlaceholderValue($placeholder) {
        if ($placeholder === 'NC_DOMAIN') {
            return $this->configurationManager->GetDomain();
        } elseif ($placeholder === 'NC_BASE_DN') {
            return $this->configurationManager->GetBaseDN();
        } elseif ($placeholder === 'AIO_TOKEN') {
            return $this->configurationManager->GetToken();
        } elseif ($placeholder === 'BORGBACKUP_REMOTE_REPO') {
            return $this->configurationManager->GetBorgRemoteRepo();
        } elseif ($placeholder === 'BORGBACKUP_MODE') {
            return $this->configurationManager->GetBackupMode();
        } elseif ($placeholder === 'AIO_URL') {
            return $this->configurationManager->GetAIOURL();
        } elseif ($placeholder === 'SELECTED_RESTORE_TIME') {
            return $this->configurationManager->GetSelectedRestoreTime();
        } elseif ($placeholder === 'RESTORE_EXCLUDE_PREVIEWS') {
            return $this->configurationManager->GetRestoreExcludePreviews();
        } elseif ($placeholder === 'APACHE_PORT') {
            return $this->configurationManager->GetApachePort();
        } elseif ($placeholder === 'TALK_PORT') {
            return $this->configurationManager->GetTalkPort();
        } elseif ($placeholder === 'NEXTCLOUD_MOUNT') {
            return $this->configurationManager->GetNextcloudMount();
        } elseif ($placeholder === 'BACKUP_RESTORE_PASSWORD') {
            return $this->configurationManager->GetBorgRestorePassword();
        } elseif ($placeholder === 'CLAMAV_ENABLED') {
            if ($this->configurationManager->isClamavEnabled()) {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'TALK_RECORDING_ENABLED') {
            if ($this->configurationManager->isTalkRecordingEnabled()) {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'ONLYOFFICE_ENABLED') {
            if ($this->configurationManager->isOnlyofficeEnabled()) {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'COLLABORA_ENABLED') {
            if ($this->configurationManager->isCollaboraEnabled()) {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'TALK_ENABLED') {
            if ($this->configurationManager->isTalkEnabled()) {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'UPDATE_NEXTCLOUD_APPS') {
            if ($this->configurationManager->isDailyBackupRunning() && $this->configurationManager->areAutomaticUpdatesEnabled()) {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'TIMEZONE') {
            if ($this->configurationManager->GetTimezone() === '') {
                return 'Etc/UTC';
            } else {
                return $this->configurationManager->GetTimezone();
            }
        } elseif ($placeholder === 'COLLABORA_DICTIONARIES') {
            if ($this->configurationManager->GetCollaboraDictionaries() === '') {
                return 'de_DE en_GB en_US es_ES fr_FR it nl pt_BR pt_PT ru';
            } else {
                return $this->configurationManager->GetCollaboraDictionaries();
            }
        } elseif ($placeholder === 'IMAGINARY_ENABLED') {
            if ($this->configurationManager->isImaginaryEnabled()) {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'FULLTEXTSEARCH_ENABLED') {
            if ($this->configurationManager->isFulltextsearchEnabled()) {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'DOCKER_SOCKET_PROXY_ENABLED') {
            if ($this->configurationManager->isDockerSocketProxyEnabled()) {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'NEXTCLOUD_UPLOAD_LIMIT') {
            return $this->configurationManager->GetNextcloudUploadLimit();
        } elseif ($placeholder === 'NEXTCLOUD_MEMORY_LIMIT') {
            return $this->configurationManager->GetNextcloudMemoryLimit();
        } elseif ($placeholder === 'NEXTCLOUD_MAX_TIME') {
            return $this->configurationManager->GetNextcloudMaxTime();
        } elseif ($placeholder === 'BORG_RETENTION_POLICY') {
            return $this->configurationManager->GetBorgRetentionPolicy();
        } elseif ($placeholder === 'FULLTEXTSEARCH_JAVA_OPTIONS') {
            return $this->configurationManager->GetFulltextsearchJavaOptions();
        } elseif ($placeholder === 'NEXTCLOUD_TRUSTED_CACERTS_DIR') {
            return $this->configurationManager->GetTrustedCacertsDir();
        } elseif ($placeholder === 'ADDITIONAL_DIRECTORIES_BACKUP') {
            if ($this->configurationManager->GetAdditionalBackupDirectoriesString() !== '') {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'BORGBACKUP_HOST_LOCATION') {
            return $this->configurationManager->GetBorgBackupHostLocation();
        } elseif ($placeholder === 'APACHE_MAX_SIZE') {
            return $this->configurationManager->GetApacheMaxSize();
        } elseif ($placeholder === 'COLLABORA_SECCOMP_POLICY') {
            return $this->configurationManager->GetCollaboraSeccompPolicy();
        } elseif ($placeholder === 'NEXTCLOUD_STARTUP_APPS') {
            return $this->configurationManager->GetNextcloudStartupApps();
        } elseif ($placeholder === 'NEXTCLOUD_ADDITIONAL_APKS') {
            return $this->configurationManager->GetNextcloudAdditionalApks();
        } elseif ($placeholder === 'NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS') {
            return $this->configurationManager->GetNextcloudAdditionalPhpExtensions();
        } elseif ($placeholder === 'INSTALL_LATEST_MAJOR') {
            if ($this->configurationManager->shouldLatestMajorGetInstalled()) {
                return 'yes';
            } else {
                return '';
            }
        } elseif ($placeholder === 'REMOVE_DISABLED_APPS') {
            if ($this->configurationManager->shouldDisabledAppsGetRemoved()) {
                return 'yes';
            } else {
                return '';
            }
            // Allow to get local ip-address of database container which allows to talk to it even in host mode (the container that requires this needs to be started first then)
        } elseif ($placeholder === 'AIO_DATABASE_HOST') {
            return gethostbyname('nextcloud-aio-database');
            // Allow to get local ip-address of caddy container and add it to trusted proxies automatically
        } elseif ($placeholder === 'CADDY_IP_ADDRESS') {
            $communityContainers = $this->configurationManager->GetEnabledCommunityContainers();
            if (in_array('caddy', $communityContainers, true)) {
                return gethostbyname('nextcloud-aio-caddy');
            } else {
                return '';
            }
        } elseif ($placeholder === 'WHITEBOARD_ENABLED') {
            if ($this->configurationManager->isWhiteboardEnabled()) {
                return 'yes';
            } else {
                return '';
            }
        } else {
            $secret = $this->configurationManager->GetSecret($placeholder);
            if ($secret === "") {
                throw new \Exception("The secret " . $placeholder . " is empty. Cannot substitute its value. Please check if it is defined in secrets of containers.json.");
            }
            return $secret;
        }
    }

    private function isContainerUpdateAvailable(string $id): string {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        $updateAvailable = "";
        if ($container->GetUpdateState() === VersionState::Different) {
            $updateAvailable = '1';
        }
        foreach ($container->GetDependsOn() as $dependency) {
            $updateAvailable .= $this->isContainerUpdateAvailable($dependency);
        }
        return $updateAvailable;
    }

    public function isAnyUpdateAvailable(): bool {
        // return early if instance is not installed
        if (!$this->configurationManager->wasStartButtonClicked()) {
            return false;
        }
        $id = 'nextcloud-aio-apache';

        if ($this->isContainerUpdateAvailable($id) !== "") {
            return true;
        } else {
            return false;
        }
    }

    private function getBackupVolumes(string $id): string {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        $backupVolumes = '';
        foreach ($container->GetBackupVolumes() as $backupVolume) {
            $backupVolumes .= $backupVolume . ' ';
        }
        foreach ($container->GetDependsOn() as $dependency) {
            $backupVolumes .= $this->getBackupVolumes($dependency);
        }
        return $backupVolumes;
    }

    private function getAllBackupVolumes(): array {
        $id = 'nextcloud-aio-apache';
        $backupVolumesArray = explode(' ', $this->getBackupVolumes($id));
        return array_unique($backupVolumesArray);
    }

    private function GetNextcloudExecCommands(string $id): string {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        $nextcloudExecCommands = '';
        foreach ($container->GetNextcloudExecCommands() as $execCommand) {
            $nextcloudExecCommands .= $execCommand . PHP_EOL;
        }
        foreach ($container->GetDependsOn() as $dependency) {
            $nextcloudExecCommands .= $this->GetNextcloudExecCommands($dependency);
        }
        return $nextcloudExecCommands;
    }

    private function GetAllNextcloudExecCommands(): string {
        $id = 'nextcloud-aio-apache';
        return 'NEXTCLOUD_EXEC_COMMANDS=' . $this->GetNextcloudExecCommands($id);
    }

    private function GetRepoDigestsOfContainer(string $containerName): ?array {
        try {
            $containerUrl = $this->BuildApiUrl(sprintf('containers/%s/json', $containerName));
            $containerOutput = json_decode($this->guzzleClient->get($containerUrl)->getBody()->getContents(), true);
            $imageName = $containerOutput['Image'];

            $imageUrl = $this->BuildApiUrl(sprintf('images/%s/json', $imageName));
            $imageOutput = json_decode($this->guzzleClient->get($imageUrl)->getBody()->getContents(), true);

            if (!isset($imageOutput['RepoDigests'])) {
                error_log('RepoDigests is not set of container ' . $containerName);
                return null;
            }

            if (!is_array($imageOutput['RepoDigests'])) {
                error_log('RepoDigests of ' . $containerName . ' is not an array which is not allowed!');
                return null;
            }

            $repoDigestArray = [];
            $oneDigestGiven = false;
            foreach ($imageOutput['RepoDigests'] as $repoDigest) {
                $digestPosition = strpos($repoDigest, '@');
                if ($digestPosition === false) {
                    error_log('Somehow the RepoDigest of ' . $containerName . ' does not contain a @.');
                    return null;
                }
                $repoDigestArray[] = substr($repoDigest, $digestPosition + 1);
                $oneDigestGiven = true;
            }

            if ($oneDigestGiven) {
                return $repoDigestArray;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function GetCurrentImageName(): string {
        $cacheKey = 'aio-image-name';
        $imageName = apcu_fetch($cacheKey);
        if ($imageName !== false && is_string($imageName)) {
            return $imageName;
        }

        $containerName = 'nextcloud-aio-mastercontainer';
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', $containerName));
        try {
            $output = json_decode($this->guzzleClient->get($url)->getBody()->getContents(), true);
            $imageNameArray = explode(':', $output['Config']['Image']);
            if (count($imageNameArray) === 2) {
                $imageName = $imageNameArray[0];
            } else {
                error_log("No tag was found when getting the current channel. You probably did not follow the documentation correctly. Changing the imageName to the default " . $output['Config']['Image']);
                $imageName = $output['Config']['Image'];
            }
            apcu_add($cacheKey, $imageName);
            return $imageName;
        } catch (\Exception $e) {
            error_log('Could not get current imageName ' . $e->getMessage());
        }

        return 'nextcloud/all-in-one';
    }

    public function GetCurrentChannel(): string {
        $cacheKey = 'aio-ChannelName';
        $channelName = apcu_fetch($cacheKey);
        if ($channelName !== false && is_string($channelName)) {
            return $channelName;
        }

        $containerName = 'nextcloud-aio-mastercontainer';
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', $containerName));
        try {
            $output = json_decode($this->guzzleClient->get($url)->getBody()->getContents(), true);
            $tagArray = explode(':', $output['Config']['Image']);
            if (count($tagArray) === 2) {
                $tag = $tagArray[1];
            } else {
                error_log("No tag was found when getting the current channel. You probably did not follow the documentation correctly. Changing the channel to the default 'latest'.");
                $tag = 'latest';
            }
            apcu_add($cacheKey, $tag);
            return $tag;
        } catch (\Exception $e) {
            error_log('Could not get current channel ' . $e->getMessage());
        }

        return 'latest';
    }

    public function IsMastercontainerUpdateAvailable(): bool {
        $imageName = $this->GetCurrentImageName();
        $containerName = 'nextcloud-aio-mastercontainer';

        $tag = $this->GetCurrentChannel();

        $runningDigests = $this->GetRepoDigestsOfContainer($containerName);
        if ($runningDigests === null) {
            return true;
        }
        $remoteDigest = $this->GetLatestDigestOfTag($imageName, $tag);
        if ($remoteDigest === null) {
            return false;
        }

        foreach ($runningDigests as $runningDigest) {
            if ($remoteDigest === $runningDigest) {
                return false;
            }
        }
        return true;
    }

    public function sendNotification(Container $container, string $subject, string $message, string $file = '/notify.sh'): void {
        if ($this->GetContainerStartingState($container) === ContainerState::Running) {

            $containerName = $container->GetIdentifier();

            // schedule the exec
            $url = $this->BuildApiUrl(sprintf('containers/%s/exec', urlencode($containerName)));
            $response = json_decode(
                $this->guzzleClient->request(
                    'POST',
                    $url,
                    [
                        'json' => [
                            'AttachStdout' => true,
                            'Tty' => true,
                            'Cmd' => [
                                'bash',
                                $file,
                                $subject,
                                $message
                            ],
                        ],
                    ]
                )->getBody()->getContents(),
                true
            );

            $id = $response['Id'];

            // start the exec
            $url = $this->BuildApiUrl(sprintf('exec/%s/start', $id));
            $this->guzzleClient->request(
                'POST',
                $url,
                [
                    'json' => [
                        'Detach' => false,
                        'Tty' => true,
                    ],
                ]
            );
        }
    }

    private function DisconnectContainerFromBridgeNetwork(string $id): void {

        $url = $this->BuildApiUrl(
            sprintf('networks/%s/disconnect', 'bridge')
        );

        try {
            $this->guzzleClient->request(
                'POST',
                $url,
                [
                    'json' => [
                        'container' => $id,
                    ],
                ]
            );
        } catch (RequestException $e) {
        }
    }

    private function ConnectContainerIdToNetwork(string $id, string $internalPort, string $network = 'nextcloud-aio', bool $createNetwork = true, string $alias = ''): void {
        if ($internalPort === 'host') {
            return;
        }

        if ($createNetwork) {
            $url = $this->BuildApiUrl('networks/create');
            try {
                $this->guzzleClient->request(
                    'POST',
                    $url,
                    [
                        'json' => [
                            'Name' => $network,
                            'CheckDuplicate' => true,
                            'Driver' => 'bridge',
                            'Internal' => false,
                        ]
                    ]
                );
            } catch (RequestException $e) {
                // 409 is undocumented and gets thrown if the network already exists.
                if ($e->getCode() !== 409) {
                    throw new \Exception("Could not create the nextcloud-aio network: " . $e->getResponse()?->getBody()->getContents());
                }
            }
        }

        $url = $this->BuildApiUrl(
            sprintf('networks/%s/connect', $network)
        );
        $jsonPayload = ['Container' => $id];
        if ($alias !== '') {
            $jsonPayload['EndpointConfig'] = ['Aliases' => [$alias]];
        }

        try {
            $this->guzzleClient->request(
                'POST',
                $url,
                [
                    'json' => $jsonPayload
                ]
            );
        } catch (RequestException $e) {
            // 403 is undocumented and gets thrown if a specific container is already part of a network
            if ($e->getCode() !== 403) {
                throw $e;
            }
        }
    }

    public function ConnectMasterContainerToNetwork(): void {
        $this->ConnectContainerIdToNetwork('nextcloud-aio-mastercontainer', '');
        // Don't disconnect here since it slows down the initial login by a lot. Is getting done during cron.sh instead.
        // $this->DisconnectContainerFromBridgeNetwork('nextcloud-aio-mastercontainer');
    }

    public function ConnectContainerToNetwork(Container $container): void {
        // Add a secondary alias for domaincheck container, to keep it as similar to actual apache controller as possible.
        // If a reverse-proxy is relying on container name as hostname this allows it to operate as usual and still validate the domain
        // The domaincheck container and apache container are never supposed to be active at the same time because they use the same APACHE_PORT anyway, so this doesn't add any new constraints.
        $alias = ($container->GetIdentifier() === 'nextcloud-aio-domaincheck') ? 'nextcloud-aio-apache' : '';

        $this->ConnectContainerIdToNetwork($container->GetIdentifier(), $container->GetInternalPort(), alias: $alias);

        if ($container->GetIdentifier() === 'nextcloud-aio-apache' || $container->GetIdentifier() === 'nextcloud-aio-domaincheck') {
            $apacheAdditionalNetwork = $this->configurationManager->GetApacheAdditionalNetwork();
            if ($apacheAdditionalNetwork !== '') {
                $this->ConnectContainerIdToNetwork($container->GetIdentifier(), $container->GetInternalPort(), $apacheAdditionalNetwork, false, $alias);
            }
        }
    }

    public function StopContainer(Container $container): void {
        $url = $this->BuildApiUrl(sprintf('containers/%s/stop?t=%s', urlencode($container->GetIdentifier()), $container->GetMaxShutdownTime()));
        try {
            $this->guzzleClient->post($url);
        } catch (RequestException $e) {
            if ($e->getCode() !== 404 && $e->getCode() !== 304) {
                throw $e;
            }
        }
    }

    public function GetBackupcontainerExitCode(): int {
        $containerName = 'nextcloud-aio-borgbackup';
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', urlencode($containerName)));
        try {
            $response = $this->guzzleClient->get($url);
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return -1;
            }
            throw $e;
        }

        $responseBody = json_decode((string)$response->getBody(), true);

        $exitCode = $responseBody['State']['ExitCode'];
        if (is_int($exitCode)) {
            return $exitCode;
        } else {
            return -1;
        }
    }

    public function GetDatabasecontainerExitCode(): int {
        $containerName = 'nextcloud-aio-database';
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', urlencode($containerName)));
        try {
            $response = $this->guzzleClient->get($url);
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return -1;
            }
            throw $e;
        }

        $responseBody = json_decode((string)$response->getBody(), true);

        $exitCode = $responseBody['State']['ExitCode'];
        if (is_int($exitCode)) {
            return $exitCode;
        } else {
            return -1;
        }
    }

    public function isLoginAllowed(): bool {
        $id = 'nextcloud-aio-apache';
        $apacheContainer = $this->containerDefinitionFetcher->GetContainerById($id);
        if ($this->GetContainerStartingState($apacheContainer) === ContainerState::Running) {
            return false;
        }
        return true;
    }

    public function isBackupContainerRunning(): bool {
        $id = 'nextcloud-aio-borgbackup';
        $backupContainer = $this->containerDefinitionFetcher->GetContainerById($id);
        if ($this->GetContainerRunningState($backupContainer) === ContainerState::Running) {
            return true;
        }
        return false;
    }

    private function GetCreatedTimeOfNextcloudImage(string $imageName): ?string {
        $imageName = $imageName . ':' . $this->GetCurrentChannel();
        try {
            $imageUrl = $this->BuildApiUrl(sprintf('images/%s/json', $imageName));
            $imageOutput = json_decode($this->guzzleClient->get($imageUrl)->getBody()->getContents(), true);

            if (!isset($imageOutput['Created'])) {
                error_log('Created is not set of image ' . $imageName);
                return null;
            }

            return str_replace('T', ' ', (string)$imageOutput['Created']);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function GetAndGenerateSecretWrapper(string $secretId): string {
        return $this->configurationManager->GetAndGenerateSecret($secretId);
    }

    public function isNextcloudImageOutdated(): bool {
        $createdTime = $this->GetCreatedTimeOfNextcloudImage('ghcr.io/nextcloud-releases/aio-nextcloud');

        if ($createdTime === null) {
            $createdTime = $this->GetCreatedTimeOfNextcloudImage('nextcloud/aio-nextcloud');
        }

        if ($createdTime === null) {
            return false;
        }

        // If the image is older than 90 days, it is outdated.
        if ((time() - (60 * 60 * 24 * 90)) > strtotime($createdTime)) {
            return true;
        }

        return false;
    }

    public function GetLatestDigestOfTag(string $imageName, string $tag): ?string {
        $prefix = 'ghcr.io/';
        if (str_starts_with($imageName, $prefix)) {
            return $this->gitHubContainerRegistryManager->GetLatestDigestOfTag(str_replace($prefix, '', $imageName), $tag);
        } else {
            return $this->dockerHubManager->GetLatestDigestOfTag($imageName, $tag);
        }
    }
}
