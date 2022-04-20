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
        return $container->GetContainerName() . ':' . $this->GetCurrentChannel();
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
        $tag = $this->GetCurrentChannel();

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
        if ($container->GetInternalPorts() !== null) {
            foreach($container->GetInternalPorts()->GetInternalPorts() as $internalPort) {
                $connection = @fsockopen($containerName, $internalPort, $errno, $errstr, 0.1);
                if ($connection) {
                    fclose($connection);
                    return new RunningState();
                } else {
                    return new StartingState();
                }
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
        $response = substr($line, 8) . "\n";

        while ($line !== false) {
            $line = strtok($separator);
            $response .= substr($line, 8) . "\n";
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
        foreach($container->GetVolumes()->GetVolumes() as $volume) {
            $volumeEntry = $volume->name . ':' . $volume->mountPoint;
            if($volume->isWritable) {
                $volumeEntry = $volumeEntry . ':' . 'rw';
            } else {
                $volumeEntry = $volumeEntry . ':' . 'ro';
            }

            $volumes[] = $volumeEntry;
        }

        $exposedPorts = [];
        foreach($container->GetPorts()->GetPorts() as $port) {
            $exposedPorts[$port] = null;
        }

        $requestBody = [
            'Image' => $this->BuildImageName($container),
        ];

        if(count($volumes) > 0) {
            $requestBody['HostConfig']['Binds'] = $volumes;
        }

        $envs = $container->GetEnvironmentVariables()->GetVariables();
        foreach($envs as $key => $env) {
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
                } elseif ($out[1] === 'DAILY_BACKUP_RUNNING') {
                    if ($this->configurationManager->isDailyBackupRunning()) {
                        $replacements[1] = 'yes';
                    } else {
                        $replacements[1] = '';
                    }
                } else {
                    $replacements[1] = $this->configurationManager->GetSecret($out[1]);
                }

                $envs[$key] = preg_replace($patterns, $replacements, $env);
            }
        }

        if(count($envs) > 0) {
            $requestBody['Env'] = $envs;
        }

        $requestBody['HostConfig']['RestartPolicy']['Name'] = $container->GetRestartPolicy();

        if(count($exposedPorts) > 0) {
            $requestBody['ExposedPorts'] = $exposedPorts;
            foreach($container->GetPorts()->GetPorts() as $port) {
                $portNumber = explode("/", $port);
                $requestBody['HostConfig']['PortBindings'][$port] = [
                    [
                    'HostPort' => $portNumber[0],
                    ]
                ];
            }
        }

        // Special things for the backup container which should not be exposed in the containers.json
        if ($container->GetIdentifier() === 'nextcloud-aio-borgbackup') {
            $requestBody['HostConfig']['CapAdd'] = ["SYS_ADMIN"];
            $requestBody['HostConfig']['Devices'] = [["PathOnHost" => "/dev/fuse", "PathInContainer" => "/dev/fuse", "CgroupPermissions" => "rwm"]];
            $requestBody['HostConfig']['SecurityOpt'] = ["apparmor:unconfined"];
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
        $id = 'nextcloud-aio-apache';

        if ($this->isContainerUpdateAvailable($id) !== "") {
            return true;
        } else {
            return false;
        }
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

    public function sendNotification(Container $container, string $subject, string $message) : void
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
                                '/notify.sh',
                                $subject,
                                $message
                            ],
                        ],
                    ]
                )->getBody()->getContents(),
                true
            );

            // get the id from the response
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

    public function DisconnectContainerFromNetwork(Container $container) : void
    {

        $url = $this->BuildApiUrl(
            sprintf('networks/%s/disconnect', 'nextcloud-aio')
        );

        try {
            $this->guzzleClient->request(
                'POST',
                $url,
                [
                    'json' => [
                        'container' => $container->GetIdentifier(),
                    ],
                ]
            );
        } catch (RequestException $e) {
            error_log('Could not disconnect container from network ' . $e->getMessage());
        }
    }

    private function ConnectContainerIdToNetwork(string $id) : void
    {
        $url = $this->BuildApiUrl('networks/create');
        try {
            $this->guzzleClient->request(
                'POST',
                $url,
                [
                    'json' => [
                        'name' => 'nextcloud-aio',
                        'checkDuplicate' => true,
                        'internal' => true,
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
            sprintf('networks/%s/connect', 'nextcloud-aio')
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
        $this->ConnectContainerIdToNetwork('nextcloud-aio-mastercontainer');
    }

    public function ConnectContainerToNetwork(Container $container) : void
    {
      $this->ConnectContainerIdToNetwork($container->GetIdentifier());
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
}
