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
        foreach($container->GetDependsOn() as $dependency) {
            $this->PerformRecursiveContainerStart($dependency, $pullImage);
        }

        // Don't start if container is already running
        // This is expected to happen if a container is defined in depends_on of multiple containers
        if ($container->GetRunningState() === ContainerState::Running) {
            error_log('Not starting ' . $id . ' because it was already started.');
            return;
        }

        // Skip database image pull if the last shutdown was not clean
        if ($id === 'nextcloud-aio-database') {
            if ($this->dockerActionManager->GetDatabasecontainerExitCode() > 0) {
                $pullImage = false;
                error_log('Not pulling the latest database image because the container was not correctly shut down.');
            }
        }

        // Check if registry is reachable in order to make sure that we do not try to pull an image if it is down
        // and try to mitigate issues that are arising due to that
        if ($pullImage) {
            if (!$this->dockerActionManager->isRegistryReachable($container)) {
                $pullImage = false;
                error_log('Not pulling the ' . $container->GetContainerName() . ' image for the ' . $container->GetIdentifier() . ' container because the registry does not seem to be reachable.');
            }
        }

        $this->dockerActionManager->DeleteContainer($container);
        $this->dockerActionManager->CreateVolumes($container);
        if ($pullImage) {
            $this->dockerActionManager->PullImage($container);
        }
        $this->dockerActionManager->CreateContainer($container);
        $this->dockerActionManager->StartContainer($container);
        $this->dockerActionManager->ConnectContainerToNetwork($container);
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
        $this->startBackup();
        return $response->withStatus(201)->withHeader('Location', '/');
    }

    public function startBackup() : void {
        $config = $this->configurationManager->GetConfig();
        $config['backup-mode'] = 'backup';
        $this->configurationManager->WriteConfig($config);

        $id = self::TOP_CONTAINER;
        $this->PerformRecursiveContainerStop($id);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);
    }

    public function StartBackupContainerCheck(Request $request, Response $response, array $args) : Response {
        $this->checkBackup();
        return $response->withStatus(201)->withHeader('Location', '/');
    }

    public function checkBackup() : void {
        $config = $this->configurationManager->GetConfig();
        $config['backup-mode'] = 'check';
        $this->configurationManager->WriteConfig($config);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);
    }

    public function StartBackupContainerRestore(Request $request, Response $response, array $args) : Response {
        $config = $this->configurationManager->GetConfig();
        $config['backup-mode'] = 'restore';
        $config['selected-restore-time'] = $request->getParsedBody()['selected_restore_time'] ?? '';
        if (isset($request->getParsedBody()['restore-exclude-previews'])) {
            $config['restore-exclude-previews'] = 1;
        } else {
            $config['restore-exclude-previews'] = '';
        }
        $this->configurationManager->WriteConfig($config);

        $id = self::TOP_CONTAINER;
        $this->PerformRecursiveContainerStop($id);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);

        return $response->withStatus(201)->withHeader('Location', '/');
    }

    public function StartBackupContainerCheckRepair(Request $request, Response $response, array $args) : Response {
        $config = $this->configurationManager->GetConfig();
        $config['backup-mode'] = 'check-repair';
        $this->configurationManager->WriteConfig($config);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);

        // Restore to backup check which is needed to make the UI logic work correctly
        $config = $this->configurationManager->GetConfig();
        $config['backup-mode'] = 'check';
        $this->configurationManager->WriteConfig($config);

        return $response->withStatus(201)->withHeader('Location', '/');
    }

    public function StartBackupContainerTest(Request $request, Response $response, array $args) : Response {
        $config = $this->configurationManager->GetConfig();
        $config['backup-mode'] = 'test';
        $config['instance_restore_attempt'] = 0;
        $this->configurationManager->WriteConfig($config);

        $id = self::TOP_CONTAINER;
        $this->PerformRecursiveContainerStop($id);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);

        return $response->withStatus(201)->withHeader('Location', '/');
    }

    public function StartContainer(Request $request, Response $response, array $args) : Response
    {
        $uri = $request->getUri();
        $host = $uri->getHost();
        $port = $uri->getPort();
        if ($port === 8000) {
            error_log('The AIO_URL-port was discovered to be 8000 which is not expected. It is now set to 443.');
            $port = 443;
        }

        if (isset($request->getParsedBody()['install_latest_major'])) {
            $installLatestMajor = 31;
        } else {
            $installLatestMajor = "";
        }

        $config = $this->configurationManager->GetConfig();
        // set AIO_URL
        $config['AIO_URL'] = $host . ':' . $port;
        // set wasStartButtonClicked
        $config['wasStartButtonClicked'] = 1;
        // set install_latest_major
        $config['install_latest_major'] = $installLatestMajor;
        $this->configurationManager->WriteConfig($config);

        // Start container
        $this->startTopContainer(true);

        // Clear apcu cache in order to check if container updates are available
        // Temporarily disabled as it leads much faster to docker rate limits
        // apcu_clear_cache();

        return $response->withStatus(201)->withHeader('Location', '/');
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
        return $response->withStatus(201)->withHeader('Location', '/');
    }

    public function startWatchtower() : void {
        $id = 'nextcloud-aio-watchtower';

        $this->PerformRecursiveContainerStart($id);
    }

    private function PerformRecursiveContainerStop(string $id) : void
    {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        // This is a hack but no better solution was found for the meantime
        // Stop Collabora first to make sure it force-saves
        // See https://github.com/nextcloud/richdocuments/issues/3799
        if ($id === self::TOP_CONTAINER) {
            $this->PerformRecursiveContainerStop('nextcloud-aio-collabora');
        }

        // Stop itself first and then all the dependencies
        $this->dockerActionManager->StopContainer($container);
        foreach($container->GetDependsOn() as $dependency) {
            $this->PerformRecursiveContainerStop($dependency);
        }
    }

    public function StopContainer(Request $request, Response $response, array $args) : Response
    {
        $id = self::TOP_CONTAINER;
        $this->PerformRecursiveContainerStop($id);

        return $response->withStatus(201)->withHeader('Location', '/');
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
