<?php
declare(strict_types=1);

namespace AIO\Docker;

use AIO\Container\Container;
use AIO\Container\ContainerState;
use AIO\Container\VersionState;
use AIO\ContainerDefinitionFetcher;
use AIO\Data\ConfigurationManager;
use AIO\Data\DataConst;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use http\Env\Response;

readonly class DockerActionManager {
    private const string API_VERSION = 'v1.44';
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
        $apiVersion = getenv('DOCKER_API_VERSION');
        if ($apiVersion === false || empty($apiVersion)) {
            $apiVersion = self::API_VERSION;
        } else {
            $apiVersion = 'v'. $apiVersion;
        }
        return sprintf('http://127.0.0.1/%s/%s', $apiVersion, $url);
    }

    private function BuildImageName(Container $container): string {
        $tag = $container->imageTag;
        if ($tag === '%AIO_CHANNEL%') {
            $tag = $this->GetCurrentChannel();
        }
        return $container->containerName . ':' . $tag;
    }

    public function GetContainerRunningState(Container $container): ContainerState {
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', urlencode($container->identifier)));
        try {
            $response = $this->guzzleClient->get($url);
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return ContainerState::ImageDoesNotExist;
            }
            throw $e;
        }

        $responseBody = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if ($responseBody['State']['Running'] === true) {
            return ContainerState::Running;
        } else {
            return ContainerState::Stopped;
        }
    }

    public function GetContainerRestartingState(Container $container): ContainerState {
        $url = $this->BuildApiUrl(sprintf('containers/%s/json', urlencode($container->identifier)));
        try {
            $response = $this->guzzleClient->get($url);
        } catch (RequestException $e) {
            if ($e->getCode() === 404) {
                return ContainerState::ImageDoesNotExist;
            }
            throw $e;
        }

        $responseBody = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if ($responseBody['State']['Restarting'] === true) {
            return ContainerState::Restarting;
        } else {
            return ContainerState::NotRestarting;
        }
    }

    public function GetContainerUpdateState(Container $container): VersionState {
        $tag = $container->imageTag;
        if ($tag === '%AIO_CHANNEL%') {
            $tag = $this->GetCurrentChannel();
        }

        $runningDigests = $this->GetRepoDigestsOfContainer($container->identifier);
        if ($runningDigests === null) {
            return VersionState::Different;
        }
        $remoteDigest = $this->GetLatestDigestOfTag($container->containerName, $tag);
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

        $containerName = $container->identifier;
        $internalPort = $container->internalPorts;
        if ($internalPort === '%APACHE_PORT%') {
            $internalPort = $this->configurationManager->apachePort;
        } elseif ($internalPort === '%TALK_PORT%') {
            $internalPort = $this->configurationManager->talkPort;
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
        $url = $this->BuildApiUrl(sprintf('containers/%s?v=true', urlencode($container->identifier)));
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
        $response = substr((string)$line, 8) . $separator;

        while ($line !== false) {
            $line = strtok($separator);
            $response .= substr((string)$line, 8) . $separator;
        }

        return $response;
    }

    public function StartContainer(Container $container, ?\Closure $addToStreamingResponseBody = null): void {
        $url = $this->BuildApiUrl(sprintf('containers/%s/start', urlencode($container->identifier)));
        try {
            if ($addToStreamingResponseBody !== null) {
                $addToStreamingResponseBody($container, "Starting container");
            }
            $this->guzzleClient->post($url);
        } catch (RequestException $e) {
            throw new \Exception("Could not start container " . $container->identifier . ": " . $e->getResponse()?->getBody()->getContents());
        }
    }

    public function CreateVolumes(Container $container): void {
        $url = $this->BuildApiUrl('volumes/create');
        foreach ($container->volumes->GetVolumes() as $volume) {
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
        foreach ($container->volumes->GetVolumes() as $volume) {
            // // NEXTCLOUD_MOUNT gets added via bind-mount later on
            // if ($container->identifier === 'nextcloud-aio-nextcloud') {
            //     if ($volume->name === $this->configurationManager->nextcloudMount) {
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

        $this->configurationManager->setAioVariables($container->aioVariables->GetVariables());

        $envs = $container->containerEnvironmentVariables->GetVariables();
        // Special thing for the nextcloud container
        if ($container->identifier === 'nextcloud-aio-nextcloud') {
            $envs[] = $this->GetAllNextcloudExecCommands();
        }
        foreach ($envs as $key => $env) {
            $envs[$key] = $this->configurationManager->replaceEnvPlaceholders($env);
        }

        if (count($envs) > 0) {
            $requestBody['Env'] = $envs;
        }

        $requestBody['HostConfig']['RestartPolicy']['Name'] = $container->restartPolicy;

        $requestBody['HostConfig']['ReadonlyRootfs'] = $container->readOnlyRootFs;

        $exposedPorts = [];
        if ($container->internalPorts !== 'host') {
            foreach ($container->ports->GetPorts() as $value) {
                $port = $value->port;
                $protocol = $value->protocol;
                if ($port === '%APACHE_PORT%') {
                    $port = $this->configurationManager->apachePort;
                    // Do not expose udp if AIO is in reverse proxy mode
                    if ($port !== '443' && $protocol === 'udp') {
                        continue;
                    }
                } else if ($port === '%TALK_PORT%') {
                    $port = $this->configurationManager->talkPort;
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
            foreach ($container->ports->GetPorts() as $value) {
                $port = $value->port;
                $protocol = $value->protocol;
                if ($port === '%APACHE_PORT%') {
                    $port = $this->configurationManager->apachePort;
                    // Do not expose udp if AIO is in reverse proxy mode
                    if ($port !== '443' && $protocol === 'udp') {
                        continue;
                    }
                } else if ($port === '%TALK_PORT%') {
                    $port = $this->configurationManager->talkPort;
                    // Skip publishing talk tcp port if it is set to 443
                    if ($port === '443' && $protocol === 'tcp') {
                        continue;
                    }
                }
                $ipBinding = $value->ipBinding;
                if ($ipBinding === '%APACHE_IP_BINDING%') {
                    $ipBinding = $this->configurationManager->apacheIpBinding;
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
        foreach ($container->devices as $device) {
            if ($device === '/dev/dri' && !$this->configurationManager->nextcloudEnableDriDevice) {
                continue;
            }
            $devices[] = ["PathOnHost" => $device, "PathInContainer" => $device, "CgroupPermissions" => "rwm"];
        }

        if (count($devices) > 0) {
            $requestBody['HostConfig']['Devices'] = $devices;
        }

        if ($container->enableNvidiaGpu && $this->configurationManager->enableNvidiaGpu) {
            $requestBody['HostConfig']['Runtime'] = 'nvidia';
            $requestBody['HostConfig']['DeviceRequests'] = [
                [
                    "Driver" => "nvidia",
                    "Count" => 1,
                    "Capabilities" => [["gpu"]],
                ]
            ];
        }

        $shmSize = $container->shmSize;
        if ($shmSize > 0) {
            $requestBody['HostConfig']['ShmSize'] = $shmSize;
        }

        $tmpfs = [];
        foreach ($container->tmpfs as $tmp) {
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

        $requestBody['HostConfig']['Init'] = $container->init;

        $maxShutDownTime = $container->maxShutdownTime;
        if ($maxShutDownTime > 0) {
            $requestBody['StopTimeout'] = $maxShutDownTime;
        }

        $capAdds = $container->capAdd;
        if (count($capAdds) > 0) {
            $requestBody['HostConfig']['CapAdd'] = $capAdds;
        }

        // Disable arp spoofing
        if (!in_array('NET_RAW', $capAdds, true)) {
            $requestBody['HostConfig']['CapDrop'] = ['NET_RAW'];
        }

        // Disable SELinux for AIO containers so that it does not break them
        $requestBody['HostConfig']['SecurityOpt'] = ["label:disable"];
        if ($container->apparmorUnconfined) {
            $requestBody['HostConfig']['SecurityOpt'] = ["apparmor:unconfined", "label:disable"];
        }

        $mounts = [];

        // Special things for the backup container which should not be exposed in the containers.json
        if (str_starts_with($container->identifier, 'nextcloud-aio-borgbackup')) {
            // Additional backup directories
            foreach ($this->getAllBackupVolumes() as $additionalBackupVolumes) {
                if ($additionalBackupVolumes !== '') {
                    $mounts[] = ["Type" => "volume", "Source" => $additionalBackupVolumes, "Target" => "/nextcloud_aio_volumes/" . $additionalBackupVolumes, "ReadOnly" => false];
                }
            }

            // Make volumes read only in case of borgbackup container. The viewer makes them writeable
            $isReadOnly = $container->identifier === 'nextcloud-aio-borgbackup';

            foreach ($this->configurationManager->getAdditionalBackupDirectoriesArray() as $additionalBackupDirectories) {
                if ($additionalBackupDirectories !== '') {
                    if (!str_starts_with($additionalBackupDirectories, '/')) {
                        $mounts[] = ["Type" => "volume", "Source" => $additionalBackupDirectories, "Target" => "/docker_volumes/" . $additionalBackupDirectories, "ReadOnly" => $isReadOnly];
                    } else {
                        $mounts[] = ["Type" => "bind", "Source" => $additionalBackupDirectories, "Target" => "/host_mounts" . $additionalBackupDirectories, "ReadOnly" => $isReadOnly, "BindOptions" => ["NonRecursive" => true]];
                    }
                }
            }

        // Special things for the talk container which should not be exposed in the containers.json
        } elseif ($container->identifier === 'nextcloud-aio-talk') {
            // This is needed due to a bug in libwebsockets used in Janus which cannot handle unlimited ulimits
            $requestBody['HostConfig']['Ulimits'] = [["Name" => "nofile", "Hard" => 200000, "Soft" => 200000]];
            // // Special things for the nextcloud container which should not be exposed in the containers.json
            // } elseif ($container->identifier === 'nextcloud-aio-nextcloud') {
            //     foreach ($container->volumes->GetVolumes() as $volume) {
            //         if ($volume->name !== $this->configurationManager->nextcloudMount) {
            //             continue;
            //         }
            //         $mounts[] = ["Type" => "bind", "Source" => $volume->name, "Target" => $volume->mountPoint, "ReadOnly" => !$volume->isWritable, "BindOptions" => [ "Propagation" => "rshared"]];
            //     }

        // Special things for the caddy community container
        } elseif ($container->identifier === 'nextcloud-aio-caddy') {
            $requestBody['HostConfig']['ExtraHosts'] = ['host.docker.internal:host-gateway'];

        // Special things for the collabora container which should not be exposed in the containers.json
        } elseif ($container->identifier === 'nextcloud-aio-collabora') {
            if (!$this->configurationManager->collaboraSeccompDisabled) {
                // Load reference seccomp profile for collabora
                $seccompProfile = (string)file_get_contents(DataConst::GetCollaboraSeccompProfilePath());
                $requestBody['HostConfig']['SecurityOpt'] = ["label:disable", "seccomp=$seccompProfile"];
            }

            // Additional Collabora options
            if ($this->configurationManager->collaboraAdditionalOptions !== '') {
                // Split the list of Collabora options, which are stored as a string but must be assigned as an array.
                // To avoid problems with whitespace or dashes in option arguments we use a regular expression
                // that splits the string at every position where a whitespace is followed by '--o:'.
                // The leading whitespace is removed in the split but the following characters are not.
                // Example: "--o:example_config1='some thing' --o:example_config2=something-else" -> ["--o:example_config1='some thing'", "--o:example_config2=something-else"] 
                $regEx = '/\s+(?=--o:)/';
                $requestBody['Cmd'] = preg_split($regEx, rtrim($this->configurationManager->collaboraAdditionalOptions));
            }
        }

        if (count($mounts) > 0) {
            $requestBody['HostConfig']['Mounts'] = $mounts;
        }

        // All AIO-managed containers should not be updated externally via watchtower but gracefully by AIO's backup and update feature.
        // Also DIUN should not send update notifications. See https://crazymax.dev/diun/providers/docker/#docker-labels 
        // Additionally set a default org.label-schema.vendor and com.docker.compose.project
        $requestBody['Labels'] = ["com.centurylinklabs.watchtower.enable" => "false", "wud.watch" => "false", "diun.enable" => "false", "org.label-schema.vendor" => "Nextcloud", "com.docker.compose.project" => "nextcloud-aio"];

        // Containers should have a fixed host name. See https://github.com/nextcloud/all-in-one/discussions/6589
        $requestBody['Hostname'] = $container->identifier;

        $url = $this->BuildApiUrl('containers/create?name=' . $container->identifier);
        try {
            $this->guzzleClient->request(
                'POST',
                $url,
                [
                    'json' => $requestBody
                ]
            );
        } catch (RequestException $e) {
            throw new \Exception("Could not create container " . $container->identifier . ": " . $e->getResponse()?->getBody()->getContents());
        }

    }

    public function isRegistryReachable(Container $container): bool {
        $tag = $container->imageTag;
        if ($tag === '%AIO_CHANNEL%') {
            $tag = $this->GetCurrentChannel();
        }

        $remoteDigest = $this->GetLatestDigestOfTag($container->containerName, $tag);

        if ($remoteDigest === null) {
            return false;
        } else {
            return true;
        }
    }

    public function PullImage(Container $container, bool $pullImage = true, ?\Closure $addToStreamingResponseBody = null): void {
        // Skip database image pull if the last shutdown was not clean
        if ($container->identifier === 'nextcloud-aio-database') {
            if ($this->GetDatabasecontainerExitCode() > 0) {
                $pullImage = false;
                error_log('Not pulling the latest database image because the container was not correctly shut down.');
            }
        }

        // Check if registry is reachable in order to make sure that we do not try to pull an image if it is down
        // and try to mitigate issues that are arising due to that
        if ($pullImage) {
            if (!$this->isRegistryReachable($container)) {
                $pullImage = false;
                error_log('Not pulling the ' . $container->containerName . ' image for the ' . $container->identifier . ' container because the registry does not seem to be reachable.');
            }
        }

        // Do not continue if $pullImage is false
        if (!$pullImage) {
            return;
        }

        $imageName = $this->BuildImageName($container);
        $encodedImageName = urlencode($imageName);
        $url = $this->BuildApiUrl(sprintf('images/create?fromImage=%s', $encodedImageName));
        $imageIsThere = true;
        try {
            if ($addToStreamingResponseBody) {
                $addToStreamingResponseBody($container, "Pulling image");
            }
            $imageUrl = $this->BuildApiUrl(sprintf('images/%s/json', $encodedImageName));
            $this->guzzleClient->get($imageUrl)->getBody()->getContents();
        } catch (\Throwable $e) {
            $imageIsThere = false;
        }

        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $this->guzzleClient->post($url);
                break;
            } catch (RequestException $e) {
                $message = "Could not pull image " . $imageName . " (attempt $attempt/$maxRetries): " . $e->getResponse()?->getBody()->getContents();
                if ($attempt === $maxRetries) {
                    if ($imageIsThere === false) {
                        throw new \Exception($message);
                    } else {
                        error_log($message);
                    }
                } else {
                    error_log($message . ' Retrying...');
                    sleep(1);
                }
            }
        }
    }

    private function isContainerUpdateAvailable(string $id): string {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        $updateAvailable = "";
        if ($container->GetUpdateState() === VersionState::Different) {
            $updateAvailable = '1';
        }
        foreach ($container->dependsOn as $dependency) {
            $updateAvailable .= $this->isContainerUpdateAvailable($dependency);
        }
        return $updateAvailable;
    }

    public function isAnyUpdateAvailable(): bool {
        // return early if instance is not installed
        if (!$this->configurationManager->wasStartButtonClicked) {
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
        foreach ($container->backupVolumes as $backupVolume) {
            $backupVolumes .= $backupVolume . ' ';
        }
        foreach ($container->dependsOn as $dependency) {
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
        foreach ($container->nextcloudExecCommands as $execCommand) {
            $nextcloudExecCommands .= $execCommand . PHP_EOL;
        }
        foreach ($container->dependsOn as $dependency) {
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
            $containerOutput = json_decode($this->guzzleClient->get($containerUrl)->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            $imageName = $containerOutput['Image'];

            $imageUrl = $this->BuildApiUrl(sprintf('images/%s/json', $imageName));
            $imageOutput = json_decode($this->guzzleClient->get($imageUrl)->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

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
            $output = json_decode($this->guzzleClient->get($url)->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
            $imageNameArray = explode(':', $output['Config']['Image']);
            if (count($imageNameArray) === 2) {
                $imageName = $imageNameArray[0];
            } else {
                error_log("Unexpected image name was found when getting the current image name of the mastercontainer. You probably did not follow the documentation correctly. Changing the image name to the default 'ghcr.io/nextcloud-releases/all-in-one'.");
                $imageName = 'ghcr.io/nextcloud-releases/all-in-one';
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
            $output = json_decode($this->guzzleClient->get($url)->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
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

            $containerName = $container->identifier;

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
                true,
                512, 
                JSON_THROW_ON_ERROR,
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
        $alias = ($container->identifier === 'nextcloud-aio-domaincheck') ? 'nextcloud-aio-apache' : '';

        $this->ConnectContainerIdToNetwork($container->identifier, $container->internalPorts, alias: $alias);

        if ($container->identifier === 'nextcloud-aio-apache' || $container->identifier === 'nextcloud-aio-domaincheck') {
            $apacheAdditionalNetwork = $this->configurationManager->getApacheAdditionalNetwork();
            if ($apacheAdditionalNetwork !== '') {
                $this->ConnectContainerIdToNetwork($container->identifier, $container->internalPorts, $apacheAdditionalNetwork, false, $alias);
            }
        }
    }

    public function StopContainer(Container $container, bool $forceStopContainer = false): void {
        if ($forceStopContainer) {
            $maxShutDownTime = 10;
        } else {
            $maxShutDownTime = $container->maxShutdownTime;
        }
        $url = $this->BuildApiUrl(sprintf('containers/%s/stop?t=%s', urlencode($container->identifier), $maxShutDownTime));
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

        $responseBody = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

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

        $responseBody = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);

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
            $imageOutput = json_decode($this->guzzleClient->get($imageUrl)->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

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
        return $this->configurationManager->getAndGenerateSecret($secretId);
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
