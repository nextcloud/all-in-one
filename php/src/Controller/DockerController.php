<?php

namespace AIO\Controller;

use AIO\Container\ContainerState;
use AIO\ContainerDefinitionFetcher;
use AIO\Docker\DockerActionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use AIO\Data\ConfigurationManager;

readonly class DockerController {
    private const string TOP_CONTAINER = 'nextcloud-aio-apache';

    public function __construct(
        private DockerActionManager           $dockerActionManager,
        private ContainerDefinitionFetcher    $containerDefinitionFetcher,
        private ConfigurationManager $configurationManager
    ) {
    }

    private function PerformRecursiveContainerStart(string $id, bool $pullImage = true) : void {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        // Start all dependencies first and then itself
        foreach($container->dependsOn as $dependency) {
            $this->PerformRecursiveContainerStart($dependency, $pullImage);
        }

        // Don't start if container is already running
        // This is expected to happen if a container is defined in depends_on of multiple containers
        if ($container->GetRunningState() === ContainerState::Running) {
            error_log('Not starting ' . $id . ' because it was already started.');
            return;
        }

        $this->dockerActionManager->DeleteContainer($container);
        $this->dockerActionManager->CreateVolumes($container);
        $this->dockerActionManager->PullImage($container, $pullImage);
        $this->dockerActionManager->CreateContainer($container);
        $this->dockerActionManager->StartContainer($container);
        $this->dockerActionManager->ConnectContainerToNetwork($container);
    }

    private function PerformRecursiveImagePull(string $id) : void {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        // Pull all dependencies first and then itself
        foreach($container->dependsOn as $dependency) {
            $this->PerformRecursiveImagePull($dependency);
        }

        $this->dockerActionManager->PullImage($container, true);
    }

    public function PullAllContainerImages(): void {

        $id = self::TOP_CONTAINER;

        $this->PerformRecursiveImagePull($id);
    }

    public function GetLogs(Request $request, Response $response, array $args) : Response
    {
        $requestParams = $request->getQueryParams();
        $id = '';
        if (isset($requestParams['id']) && is_string($requestParams['id'])) {
            $id = $requestParams['id'];
        }
        if (str_starts_with($id, 'nextcloud-aio-')) {
            $logs = $this->dockerActionManager->GetLogs($id);
        } else {
            $logs = 'Container not found.';
        }

        $body = $response->getBody();
        $body->write($logs);

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Content-Disposition', 'inline');
    }

    public function StartBackupContainerBackup(Request $request, Response $response, array $args) : Response {
        $forceStopNextcloud = true;
        $this->startBackup($forceStopNextcloud);
        return $response->withStatus(201)->withHeader('Location', '.');
    }

    public function startBackup(bool $forceStopNextcloud = false) : void {
        $this->configurationManager->SetBackupMode('backup');

        $id = self::TOP_CONTAINER;
        $this->PerformRecursiveContainerStop($id, $forceStopNextcloud);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);
    }

    public function StartBackupContainerCheck(Request $request, Response $response, array $args) : Response {
        $this->checkBackup();
        return $response->withStatus(201)->withHeader('Location', '.');
    }

    public function StartBackupContainerList(Request $request, Response $response, array $args) : Response {
        $this->listBackup();
        return $response->withStatus(201)->withHeader('Location', '.');
    }

    public function checkBackup() : void {
        $this->configurationManager->SetBackupMode('check');

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);
    }

    private function listBackup() : void {
        $this->configurationManager->SetBackupMode('list');

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);
    }

    public function StartBackupContainerRestore(Request $request, Response $response, array $args) : Response {
        $this->configurationManager->SetBackupMode('restore');
        $config = $this->configurationManager->GetConfig();
        $config['selected-restore-time'] = $request->getParsedBody()['selected_restore_time'] ?? '';
        if (isset($request->getParsedBody()['restore-exclude-previews'])) {
            $config['restore-exclude-previews'] = 1;
        } else {
            $config['restore-exclude-previews'] = '';
        }
        $this->configurationManager->WriteConfig($config);

        $id = self::TOP_CONTAINER;
        $forceStopNextcloud = true;
        $this->PerformRecursiveContainerStop($id, $forceStopNextcloud);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);

        return $response->withStatus(201)->withHeader('Location', '.');
    }

    public function StartBackupContainerCheckRepair(Request $request, Response $response, array $args) : Response {
        $this->configurationManager->SetBackupMode('check-repair');

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);

        // Restore to backup check which is needed to make the UI logic work correctly
        $this->configurationManager->SetBackupMode('check');

        return $response->withStatus(201)->withHeader('Location', '.');
    }

    public function StartBackupContainerTest(Request $request, Response $response, array $args) : Response {
        $this->configurationManager->SetBackupMode('test');
        $config = $this->configurationManager->GetConfig();
        $config['instance_restore_attempt'] = 0;
        $this->configurationManager->WriteConfig($config);

        $id = self::TOP_CONTAINER;
        $this->PerformRecursiveContainerStop($id);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);

        return $response->withStatus(201)->withHeader('Location', '.');
    }

    public function StartContainer(Request $request, Response $response, array $args) : Response
    {
        $uri = $request->getUri();
        $host = $uri->getHost();
        $port = $uri->getPort();
        $path = $request->getParsedBody()['base_path'] ?? '';
        if ($port === 8000) {
            error_log('The AIO_URL-port was discovered to be 8000 which is not expected. It is now set to 443.');
            $port = 443;
        }

        if (isset($request->getParsedBody()['install_latest_major'])) {
            $installLatestMajor = 32;
        } else {
            $installLatestMajor = "";
        }

        $config = $this->configurationManager->GetConfig();
        // set AIO_URL
        $config['AIO_URL'] = $host . ':' . (string)$port . $path;
        // set wasStartButtonClicked
        $config['wasStartButtonClicked'] = 1;
        // set install_latest_major
        $config['install_latest_major'] = $installLatestMajor;
        $this->configurationManager->WriteConfig($config);

        // Do not pull container images in case 'bypass_container_update' is set via url params
        // Needed for local testing
        $pullImage = !isset($request->getParsedBody()['bypass_container_update']);
        if ($pullImage === false) {
            error_log('WARNING: Not pulling container images. Instead, using local ones.');
        }
        // Start container
        $this->startTopContainer($pullImage);

        // Clear apcu cache in order to check if container updates are available
        // Temporarily disabled as it leads much faster to docker rate limits
        // apcu_clear_cache();

        return $response->withStatus(201)->withHeader('Location', '.');
    }

    public function startTopContainer(bool $pullImage) : void {
        $config = $this->configurationManager->GetConfig();
        // set AIO_TOKEN
        $config['AIO_TOKEN'] = bin2hex(random_bytes(24));
        $this->configurationManager->WriteConfig($config);

        // Stop domaincheck since apache would not be able to start otherwise
        $this->StopDomaincheckContainer();

        $id = self::TOP_CONTAINER;

        $this->PerformRecursiveContainerStart($id, $pullImage);
    }

    public function StartWatchtowerContainer(Request $request, Response $response, array $args) : Response {
        $this->startWatchtower();
        return $response->withStatus(201)->withHeader('Location', '.');
    }

    public function startWatchtower() : void {
        $id = 'nextcloud-aio-watchtower';

        $this->PerformRecursiveContainerStart($id);
    }

    private function PerformRecursiveContainerStop(string $id, bool $forceStopNextcloud = false) : void
    {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        // This is a hack but no better solution was found for the meantime
        // Stop Collabora first to make sure it force-saves
        // See https://github.com/nextcloud/richdocuments/issues/3799
        if ($id === self::TOP_CONTAINER && $this->configurationManager->isCollaboraEnabled()) {
            $this->PerformRecursiveContainerStop('nextcloud-aio-collabora');
        }

        // Stop itself first and then all the dependencies
        if ($id !== 'nextcloud-aio-nextcloud') {
            $this->dockerActionManager->StopContainer($container);
        } else {
            // We want to stop the Nextcloud container after 10s and not wait for the configured stop_grace_period
            $this->dockerActionManager->StopContainer($container, $forceStopNextcloud);
        }
        foreach($container->dependsOn as $dependency) {
            $this->PerformRecursiveContainerStop($dependency, $forceStopNextcloud);
        }
    }

    public function StopContainer(Request $request, Response $response, array $args) : Response
    {
        $id = self::TOP_CONTAINER;
        $forceStopNextcloud = true;
        $this->PerformRecursiveContainerStop($id, $forceStopNextcloud);

        return $response->withStatus(201)->withHeader('Location', '.');
    }

    public function stopTopContainer() : void {
        $id = self::TOP_CONTAINER;
        $this->PerformRecursiveContainerStop($id);
    }

    public function StartDomaincheckContainer() : void
    {
        # Don't start if domain is already set
        if ($this->configurationManager->GetDomain() !== '' || $this->configurationManager->wasStartButtonClicked()) {
            return;
        }

        $id = 'nextcloud-aio-domaincheck';

        $cacheKey = 'domaincheckWasStarted';

        $domaincheckContainer = $this->containerDefinitionFetcher->GetContainerById($id);
        $apacheContainer = $this->containerDefinitionFetcher->GetContainerById(self::TOP_CONTAINER);
        // Don't start if apache is already running
        if ($apacheContainer->GetRunningState() === ContainerState::Running) {
            return;
        // Don't start if domaincheck is already running
        } elseif ($domaincheckContainer->GetRunningState() === ContainerState::Running) {
            $domaincheckWasStarted = apcu_fetch($cacheKey);
            // Start domaincheck again when 10 minutes are over by not returning here
            if($domaincheckWasStarted !== false && is_string($domaincheckWasStarted)) {
                return;
            }
        }

        $this->StopDomaincheckContainer();
        try {
            $this->PerformRecursiveContainerStart($id);
        } catch (\Exception $e) {
            error_log('Could not start domaincheck container: ' . $e->getMessage());
        }

        // Cache the start for 10 minutes
        apcu_add($cacheKey, '1', 600);
    }

    private function StopDomaincheckContainer() : void
    {
        $id = 'nextcloud-aio-domaincheck';
        $this->PerformRecursiveContainerStop($id);
    }
}
