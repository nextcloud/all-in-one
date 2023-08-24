<?php

namespace AIO\Docker;

use AIO\Container\Container;
use AIO\Container\State\IContainerState;
use AIO\Container\State\ImageDoesNotExistState;
use AIO\Container\State\StartingState;
use AIO\Container\State\RunningState;
use AIO\Container\State\RestartingState;
use AIO\Container\State\NotRestartingState;
use AIO\Container\State\VersionDifferentState;
use AIO\Container\State\StoppedState;
use AIO\Container\State\VersionEqualState;
use AIO\Data\ConfigurationManager;
use GuzzleHttp\Exception\RequestException;
use AIO\ContainerDefinitionFetcher;
use http\Env\Response;

class DockerActionManager
{
    private const API_VERSION = 'v1.41';
    private \GuzzleHttp\Client $guzzleClient;
    private ConfigurationManager $configurationManager;
    private ContainerDefinitionFetcher $containerDefinitionFetcher;
    private DockerHubManager $dockerHubManager;

    public function __construct(
        ConfigurationManager  $configurationManager,
        ContainerDefinitionFetcher $containerDefinitionFetcher,
        DockerHubManager $dockerHubManager
    ) {
        $this->configurationManager = $configurationManager;
        $this->containerDefinitionFetcher = $containerDefinitionFetcher;
        $this->dockerHubManager = $dockerHubManager;
        $this->guzzleClient = new \GuzzleHttp\Client(
            [
                'curl' => [
                    CURLOPT_UNIX_SOCKET_PATH => '/var/run/docker.sock',

                ],
            ]
        );
    }

    private function BuildApiUrl(string $url) : string {
        return sprintf('http://localhost/%s/%s', self::API_VERSION, $url);
    }

    private function BuildImageName(Container $container) : string {
        $tag = $container->GetImageTag();
        if ($tag === '') {
            $tag = $this->GetCurrentChannel();
        }
        return $container->GetContainerName() . ':' . $tag;
    }

    public function GetContainerRunningState(Container $container) : IContainerState
    {
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', urlencode($container->GetIdentifier())));
        try {
            $response = $this->guzzleClient->get($url);
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return new ImageDoesNotExistState();
            }
            throw $e;
        }

        $responseBody = json_decode((string)$response->getBody(), true);

        if ($responseBody['State']['Running'] === true) {
            return new RunningState();
        } else {
            return new StoppedState();
        }
    }

    public function GetContainerRestartingState(Container $container) : IContainerState
    {
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', urlencode($container->GetIdentifier())));
        try {
            $response = $this->guzzleClient->get($url);
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return new ImageDoesNotExistState();
            }
            throw $e;
        }

        $responseBody = json_decode((string)$response->getBody(), true);

        if ($responseBody['State']['Restarting'] === true) {
            return new RestartingState();
        } else {
            return new NotRestartingState();
        }
    }

    public function GetContainerUpdateState(Container $container) : IContainerState
    {
        $tag = $container->GetImageTag();
        if ($tag === '') {
            $tag = $this->GetCurrentChannel();
        }

        $runningDigests = $this->GetRepoDigestsOfContainer($container->GetIdentifier());
        if ($runningDigests === null) {
            return new VersionDifferentState();
        }
        $remoteDigest = $this->dockerHubManager->GetLatestDigestOfTag($container->GetContainerName(), $tag);
        if ($remoteDigest === null) {
            return new VersionEqualstate();
        }

        foreach($runningDigests as $runningDigest) {
            if ($runningDigest === $remoteDigest) {
                return new VersionEqualState();
            }
        }
        return new VersionDifferentState();
    }

    public function GetContainerStartingState(Container $container) : IContainerState
    {
        $runningState = $this->GetContainerRunningState($container);
        if ($runningState instanceof StoppedState) {
            return new StoppedState();
        } elseif ($runningState instanceof ImageDoesNotExistState) {
            return new ImageDoesNotExistState();
        }

        $containerName = $container->GetIdentifier();
        $internalPort = $container->GetInternalPort();
        if($internalPort === '%APACHE_PORT%') {
            $internalPort = $this->configurationManager->GetApachePort();
        } elseif($internalPort === '%TALK_PORT%') {
            $internalPort = $this->configurationManager->GetTalkPort();
        }
        
        if ($internalPort !== "" && $internalPort !== 'host') {
            $connection = @fsockopen($containerName, (int)$internalPort, $errno, $errstr, 0.2);
            if ($connection) {
                fclose($connection);
                return new RunningState();
            } else {
                return new StartingState();
            }
        } else {
            return new RunningState();
        }
    }

    public function DeleteContainer(Container $container) : void {
        $url = $this->BuildApiUrl(sprintf('containers/%s?v=true', urlencode($container->GetIdentifier())));
        try {
            $this->guzzleClient->delete($url);
        } catch (RequestException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }
    }

    public function GetLogs(string $id) : string
    {
        $url = $this->BuildApiUrl(
            sprintf(
                'containers/%s/logs?stdout=true&stderr=true',
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

    public function StartContainer(Container $container) : void {
        $url = $this->BuildApiUrl(sprintf('containers/%s/start', urlencode($container->GetIdentifier())));
        $this->guzzleClient->post($url);
    }

    public function CreateVolumes(Container $container): void
    {
        $url = $this->BuildApiUrl('volumes/create');
        foreach($container->GetVolumes()->GetVolumes() as $volume) {
            $forbiddenChars = [
                '/',
            ];

            if ($volume->name === 'nextcloud_aio_nextcloud_datadir' || $volume->name === 'nextcloud_aio_backupdir') {
                return;
            }

            $firstChar = substr($volume->name, 0, 1);
            if(!in_array($firstChar, $forbiddenChars)) {
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

    public function CreateContainer(Container $container) : void {
        $volumes = [];
        foreach ($container->GetVolumes()->GetVolumes() as $volume) {
            // NEXTCLOUD_MOUNT gets added via bind-mount later on
            if ($container->GetIdentifier() === 'nextcloud-aio-nextcloud') {
                if ($volume->name === $this->configurationManager->GetNextcloudMount()) {
                    continue;
                }
            }

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

        foreach($container->GetSecrets() as $secret) {
            $this->configurationManager->GetAndGenerateSecret($secret);
        }

        $envs = $container->GetEnvironmentVariables()->GetVariables();
        // Special thing for the nextcloud container
        if ($container->GetIdentifier() === 'nextcloud-aio-nextcloud') {
            $envs[] = $this->GetAllNextcloudExecCommands();
        }
        foreach($envs as $key => $env) {
            // TODO: This whole block below is a hack and needs to get reworked in order to support multiple substitutions per line by default for all envs
            if (str_starts_with($env, 'extra_params=')) {
                $env = str_replace('%COLLABORA_SECCOMP_POLICY%', $this->configurationManager->GetCollaboraSeccompPolicy(), $env);
                $env = str_replace('%NC_DOMAIN%', $this->configurationManager->GetDomain(), $env);
                $envs[$key] = $env;
                continue;
            }

            // Original implementation
            $patterns = ['/%(.*)%/'];

            if(preg_match($patterns[0], $env, $out) === 1) {
                $replacements = array();

                if($out[1] === 'NC_DOMAIN') {
                    $replacements[1] = $this->configurationManager->GetDomain();
                } elseif ($out[1] === 'AIO_TOKEN') {
                    $replacements[1] = $this->configurationManager->GetToken();
                } elseif ($out[1] === 'BORGBACKUP_MODE') {
                    $replacements[1] = $this->configurationManager->GetBackupMode();
                } elseif ($out[1] === 'AIO_URL') {
                    $replacements[1] = $this->configurationManager->GetAIOURL();
                } elseif ($out[1] === 'SELECTED_RESTORE_TIME') {
                    $replacements[1] = $this->configurationManager->GetSelectedRestoreTime();
                } elseif ($out[1] === 'APACHE_PORT') {
                    $replacements[1] = $this->configurationManager->GetApachePort();
                } elseif ($out[1] === 'TALK_PORT') {
                    $replacements[1] = $this->configurationManager->GetTalkPort();
                } elseif ($out[1] === 'NEXTCLOUD_MOUNT') {
                    $replacements[1] = $this->configurationManager->GetNextcloudMount();
                } elseif ($out[1] === 'BACKUP_RESTORE_PASSWORD') {
                    $replacements[1] = $this->configurationManager->GetBorgRestorePassword();
                } elseif ($out[1] === 'CLAMAV_ENABLED') {
                    if ($this->configurationManager->isClamavEnabled()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } elseif ($out[1] === 'TALK_RECORDING_ENABLED') {
                    if ($this->configurationManager->isTalkRecordingEnabled()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } elseif ($out[1] === 'ONLYOFFICE_ENABLED') {
                    if ($this->configurationManager->isOnlyofficeEnabled()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } elseif ($out[1] === 'COLLABORA_ENABLED') {
                    if ($this->configurationManager->isCollaboraEnabled()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } elseif ($out[1] === 'TALK_ENABLED') {
                    if ($this->configurationManager->isTalkEnabled()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } elseif ($out[1] === 'UPDATE_NEXTCLOUD_APPS') {
                    if ($this->configurationManager->isDailyBackupRunning() && $this->configurationManager->areAutomaticUpdatesEnabled()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } elseif ($out[1] === 'TIMEZONE') {
                    if ($this->configurationManager->GetTimezone() === '') {
                        $replacements[1] = 'Etc/UTC';
                    } else {
                        $replacements[1] = $this->configurationManager->GetTimezone();
                    }
                } elseif ($out[1] === 'COLLABORA_DICTIONARIES') {
                    if ($this->configurationManager->GetCollaboraDictionaries() === '') {
                        $replacements[1] = 'de_DE en_GB en_US es_ES fr_FR it nl pt_BR pt_PT ru';
                    } else {
                        $replacements[1] = $this->configurationManager->GetCollaboraDictionaries();
                    }
                } elseif ($out[1] === 'IMAGINARY_ENABLED') {
                    if ($this->configurationManager->isImaginaryEnabled()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } elseif ($out[1] === 'FULLTEXTSEARCH_ENABLED') {
                    if ($this->configurationManager->isFulltextsearchEnabled()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } elseif ($out[1] === 'DOCKER_SOCKET_PROXY_ENABLED') {
                    if ($this->configurationManager->isDockerSocketProxyEnabled()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } elseif ($out[1] === 'NEXTCLOUD_UPLOAD_LIMIT') {
                    $replacements[1] = $this->configurationManager->GetNextcloudUploadLimit();
                } elseif ($out[1] === 'NEXTCLOUD_MEMORY_LIMIT') {
                    $replacements[1] = $this->configurationManager->GetNextcloudMemoryLimit();
                } elseif ($out[1] === 'NEXTCLOUD_MAX_TIME') {
                    $replacements[1] = $this->configurationManager->GetNextcloudMaxTime();
                } elseif ($out[1] === 'BORG_RETENTION_POLICY') {
                    $replacements[1] = $this->configurationManager->GetBorgRetentionPolicy();
                } elseif ($out[1] === 'NEXTCLOUD_TRUSTED_CACERTS_DIR') {
                    $replacements[1] = $this->configurationManager->GetTrustedCacertsDir();
                } elseif ($out[1] === 'ADDITIONAL_DIRECTORIES_BACKUP') {
                    if ($this->configurationManager->GetAdditionalBackupDirectoriesString() !== '') {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } elseif ($out[1] === 'BORGBACKUP_HOST_LOCATION') {
                    $replacements[1] = $this->configurationManager->GetBorgBackupHostLocation();
                } elseif ($out[1] === 'APACHE_MAX_SIZE') {
                    $replacements[1] = $this->configurationManager->GetApacheMaxSize();
                } elseif ($out[1] === 'COLLABORA_SECCOMP_POLICY') {
                    $replacements[1] = $this->configurationManager->GetCollaboraSeccompPolicy();
                } elseif ($out[1] === 'NEXTCLOUD_STARTUP_APPS') {
                    $replacements[1] = $this->configurationManager->GetNextcloudStartupApps();
                } elseif ($out[1] === 'NEXTCLOUD_ADDITIONAL_APKS') {
                    $replacements[1] = $this->configurationManager->GetNextcloudAdditionalApks();
                } elseif ($out[1] === 'NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS') {
                    $replacements[1] = $this->configurationManager->GetNextcloudAdditionalPhpExtensions();
                } elseif ($out[1] === 'INSTALL_LATEST_MAJOR') {
                    if ($this->configurationManager->shouldLatestMajorGetInstalled()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } else {
                    $secret = $this->configurationManager->GetSecret($out[1]);
                    if ($secret === "") {
                        throw new \Exception("The secret " . $out[1] . " is empty. Cannot substitute its value. Please check if it is defined in secrets of containers.json.");
                    }
                    $replacements[1] = $secret;
                }

                $envs[$key] = preg_replace($patterns, $replacements, $env);
            }
        }

        if(count($envs) > 0) {
            $requestBody['Env'] = $envs;
        }

        $requestBody['HostConfig']['RestartPolicy']['Name'] = $container->GetRestartPolicy();

        $requestBody['HostConfig']['ReadonlyRootfs'] = $container->GetReadOnlySetting();
 
        $exposedPorts = [];
        if ($container->GetInternalPort() !== 'host') {
            foreach($container->GetPorts()->GetPorts() as $value) {
                $portWithProtocol = $value->port . '/' . $value->protocol;
                $exposedPorts[$portWithProtocol] = null;
            }
            if ($container->GetIdentifier() !== 'nextcloud-aio-docker-socket-proxy') {
                $requestBody['HostConfig']['NetworkMode'] = 'nextcloud-aio';
            } else {
                $requestBody['HostConfig']['NetworkMode'] = 'nextcloud-aio-docker-socket-proxy-network';
            }
        } else {
            $requestBody['HostConfig']['NetworkMode'] = 'host';
        }

        if(count($exposedPorts) > 0) {
            $requestBody['ExposedPorts'] = $exposedPorts;
            foreach ($container->GetPorts()->GetPorts() as $value) {
                $port = $value->port;
                $ipBinding = $value->ipBinding;
                $protocol = $value->protocol;
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
        foreach($container->GetDevices() as $device) {
            if ($device === '/dev/dri' && ! $this->configurationManager->isDriDeviceEnabled()) {
                continue;
            }
            $devices[] = ["PathOnHost" => $device, "PathInContainer" => $device, "CgroupPermissions" => "rwm"];
        }

        if (count($devices) > 0) {
            $requestBody['HostConfig']['Devices'] = $devices;
        }

        $shmSize = $container->GetShmSize();
        if ($shmSize > 0) {
            $requestBody['HostConfig']['ShmSize'] = $shmSize;
        }

        $tmpfs = [];
        foreach($container->GetTmpfs() as $tmp) {
            $mode = "";
            if (str_contains($tmp, ':')) {
                $mode = explode(':', $tmp)[1];
                $tmp = explode(':', $tmp)[0];
            }
            $tmpfs[$tmp] = $mode;
        }
        if (count($tmpfs) > 0) {
            $requestBody['HostConfig']['Tmpfs'] =  $tmpfs;
        }

        $requestBody['HostConfig']['Init'] = $container->GetInit();

        $capAdds = $container->GetCapAdds();
        if (count($capAdds) > 0) {
            $requestBody['HostConfig']['CapAdd'] = $capAdds;
        }

        if ($container->isApparmorUnconfined()) {
            $requestBody['HostConfig']['SecurityOpt'] = ["apparmor:unconfined"];
        }

        $mounts = [];

        // Special things for the backup container which should not be exposed in the containers.json
        if ($container->GetIdentifier() === 'nextcloud-aio-borgbackup') {
            // Additional backup directories
            foreach ($this->getAllBackupVolumes() as $additionalBackupVolumes) {
                if ($additionalBackupVolumes !== '') {
                    $mounts[] = ["Type" => "volume", "Source" => $additionalBackupVolumes, "Target" => "/nextcloud_aio_volumes/" . $additionalBackupVolumes, "ReadOnly" => false];
                }
            }
            foreach ($this->configurationManager->GetAdditionalBackupDirectoriesArray() as $additionalBackupDirectories) {
                if ($additionalBackupDirectories !== '') {
                    if (!str_starts_with($additionalBackupDirectories, '/')) {
                        $mounts[] = ["Type" => "volume", "Source" => $additionalBackupDirectories, "Target" => "/docker_volumes/" . $additionalBackupDirectories, "ReadOnly" => true];
                    } else {
                        $mounts[] = ["Type" => "bind", "Source" => $additionalBackupDirectories, "Target" => "/host_mounts" . $additionalBackupDirectories, "ReadOnly" => true, "BindOptions" => ["NonRecursive" => true]];
                    }
                }
            }
        // Special things for the talk container which should not be exposed in the containers.json
        } elseif ($container->GetIdentifier() === 'nextcloud-aio-talk') {
            // This is needed due to a bug in libwebsockets which cannot handle unlimited ulimits
            $requestBody['HostConfig']['Ulimits'] = [["Name" => "nofile", "Hard" => 200000, "Soft" => 200000]];
        // Special things for the nextcloud container which should not be exposed in the containers.json
        } elseif ($container->GetIdentifier() === 'nextcloud-aio-nextcloud') {
            foreach ($container->GetVolumes()->GetVolumes() as $volume) {
                if ($volume->name !== $this->configurationManager->GetNextcloudMount()) {
                    continue;
                }
                $mounts[] = ["Type" => "bind", "Source" => $volume->name, "Target" => $volume->mountPoint, "ReadOnly" => !$volume->isWritable, "BindOptions" => [ "Propagation" => "rshared"]];
            }
        // Special things for the watchtower and docker-socket-proxy container which should not be exposed in the containers.json
        } elseif ($container->GetIdentifier() === 'nextcloud-aio-watchtower' || $container->GetIdentifier() === 'nextcloud-aio-docker-socket-proxy') {
            $requestBody['HostConfig']['SecurityOpt'] = ["label=disabled"];
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
            throw $e;
        }

    }

    public function PullContainer(Container $container) : void
    {
        $url = $this->BuildApiUrl(sprintf('images/create?fromImage=%s', urlencode($this->BuildImageName($container))));
        try {
            $this->guzzleClient->post($url);
        } catch (RequestException $e) {
            error_log('Could not get image ' . $this->BuildImageName($container) . ' from docker hub. Probably due to rate limits. ' . $e->getMessage());
            // Don't exit here because it is possible that the image is already present 
            // and we ran into docker hub limits.
            // We will exit later if not image should be available.
        }
    }

    private function isContainerUpdateAvailable(string $id) : string
    {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        $updateAvailable = "";
        if ($container->GetUpdateState() instanceof VersionDifferentState) {
            $updateAvailable = '1';
        }
        foreach ($container->GetDependsOn() as $dependency) {
            $updateAvailable .= $this->isContainerUpdateAvailable($dependency);
        }
        return $updateAvailable;
    }

    public function isAnyUpdateAvailable() : bool {
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

    private function getBackupVolumes(string $id) : string
    {
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

    private function getAllBackupVolumes() : array {
        $id = 'nextcloud-aio-apache';
        $backupVolumesArray = explode(' ', $this->getBackupVolumes($id));
        return array_unique($backupVolumesArray);
    }

    private function GetNextcloudExecCommands(string $id) : string
    {
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

    private function GetAllNextcloudExecCommands() : string
    {
        $id = 'nextcloud-aio-apache';
        return 'NEXTCLOUD_EXEC_COMMANDS=' . $this->GetNextcloudExecCommands($id);
    }

    private function GetRepoDigestsOfContainer(string $containerName) : ?array {
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
            foreach($imageOutput['RepoDigests'] as $repoDigest) {
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

    public function GetCurrentChannel() : string {
        $cacheKey = 'aio-ChannelName';
        $channelName = apcu_fetch($cacheKey);
        if($channelName !== false && is_string($channelName)) {
            return $channelName;
        }

        $containerName = 'nextcloud-aio-mastercontainer';
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', $containerName));
        try {
            $output = json_decode($this->guzzleClient->get($url)->getBody()->getContents(), true);
            $containerChecksum = $output['Image'];
            $tagArray = explode(':', $output['Config']['Image']);
            $tag = $tagArray[1];
            apcu_add($cacheKey, $tag);
            /**
             * @psalm-suppress TypeDoesNotContainNull
             * @psalm-suppress DocblockTypeContradiction
             */
            if ($tag === null) {
                error_log("No tag was found when getting the current channel. You probably did not follow the documentation correctly. Changing the channel to the default 'latest'.");
                $tag = 'latest';
            }
            return $tag;
        } catch (\Exception $e) {
            error_log('Could not get current channel ' . $e->getMessage());
        }

        return 'latest';
    }

    public function IsMastercontainerUpdateAvailable() : bool
    {
        $imageName = 'nextcloud/all-in-one';
        $containerName = 'nextcloud-aio-mastercontainer';

        $tag = $this->GetCurrentChannel();

        $runningDigests = $this->GetRepoDigestsOfContainer($containerName);
        if ($runningDigests === null) {
            return true;
        }
        $remoteDigest = $this->dockerHubManager->GetLatestDigestOfTag($imageName, $tag);
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

    public function sendNotification(Container $container, string $subject, string $message, string $file = '/notify.sh') : void
    {
        if ($this->GetContainerStartingState($container) instanceof RunningState) {

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

    private function DisconnectContainerFromBridgeNetwork(string $id) : void
    {

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

    private function ConnectContainerIdToNetwork(string $id, string $internalPort, string $network = 'nextcloud-aio') : void
    {
        if ($internalPort === 'host') {
            return;
        }

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
                throw $e;
            }
        }

        $url = $this->BuildApiUrl(
            sprintf('networks/%s/connect', $network)
        );
        try {
            $this->guzzleClient->request(
                'POST',
                $url,
                [
                    'json' => [
                        'container' => $id,
                    ]
                ]
            );
        } catch (RequestException $e) {
            // 403 is undocumented and gets thrown if a specific container is already part of a network
            if ($e->getCode() !== 403) {
                throw $e;
            }
        }
    }

    public function ConnectMasterContainerToNetwork() : void
    {
        $this->ConnectContainerIdToNetwork('nextcloud-aio-mastercontainer', '');
        $this->ConnectContainerIdToNetwork('nextcloud-aio-mastercontainer', '', 'nextcloud-aio-docker-socket-proxy-network');
        // Don't disconnect here since it slows down the initial login by a lot. Is getting done during cron.sh instead.
        // $this->DisconnectContainerFromBridgeNetwork('nextcloud-aio-mastercontainer');
    }

    public function ConnectContainerToNetwork(Container $container) : void
    {
        if ($container->GetIdentifier() !== 'nextcloud-aio-docker-socket-proxy') {
            $this->ConnectContainerIdToNetwork($container->GetIdentifier(), $container->GetInternalPort());
        }
        if ($container->GetIdentifier() === 'nextcloud-aio-nextcloud' || $container->GetIdentifier() === 'nextcloud-aio-docker-socket-proxy') {
            $this->ConnectContainerIdToNetwork($container->GetIdentifier(), $container->GetInternalPort(), 'nextcloud-aio-docker-socket-proxy-network');
        }
    }

    public function StopContainer(Container $container) : void {
        $url = $this->BuildApiUrl(sprintf('containers/%s/stop?t=%s', urlencode($container->GetIdentifier()), $container->GetMaxShutdownTime()));
        try {
            $this->guzzleClient->post($url);
        } catch (RequestException $e) {
            if ($e->getCode() !== 404 && $e->getCode() !== 304) {
                throw $e;
            }
        }
    }

    public function GetBackupcontainerExitCode() : int
    {
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

    public function GetDatabasecontainerExitCode() : int
    {
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

    public function isLoginAllowed() : bool {
        $id = 'nextcloud-aio-apache';
        $apacheContainer = $this->containerDefinitionFetcher->GetContainerById($id);
        if ($this->GetContainerStartingState($apacheContainer) instanceof RunningState) {
            return false;
        }
        return true;
    }

    public function isBackupContainerRunning() : bool {
        $id = 'nextcloud-aio-borgbackup';
        $backupContainer = $this->containerDefinitionFetcher->GetContainerById($id);
        if ($this->GetContainerRunningState($backupContainer) instanceof RunningState) {
            return true;
        }
        return false;
    }

    private function GetCreatedTimeOfNextcloudImage() : ?string {
        $imageName = 'nextcloud/aio-nextcloud' . ':' . $this->GetCurrentChannel();
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

    public function isNextcloudImageOutdated() : bool {
        $createdTime = $this->GetCreatedTimeOfNextcloudImage();

        if ($createdTime === null) {
            return false;
        }

        // If the image is older than 90 days, it is outdated.
        if ((time() - (60 * 60 * 24 * 90)) > strtotime($createdTime)) {
            return true;
        }

        return false;
    }
}
