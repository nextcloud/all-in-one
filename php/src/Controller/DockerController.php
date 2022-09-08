<?php

namespace AIO\Controller;

use AIO\Container\State\RunningState;
use AIO\ContainerDefinitionFetcher;
use AIO\Docker\DockerActionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use AIO\Data\ConfigurationManager;

class DockerController
{
    private DockerActionManager $dockerActionManager;
    private ContainerDefinitionFetcher $containerDefinitionFetcher;
    private const TOP_CONTAINER = 'nextcloud-aio-apache';
    private ConfigurationManager $configurationManager;

    public function __construct(
        DockerActionManager $dockerActionManager,
        ContainerDefinitionFetcher $containerDefinitionFetcher,
        ConfigurationManager $configurationManager
    ) {
        $this->dockerActionManager = $dockerActionManager;
        $this->containerDefinitionFetcher = $containerDefinitionFetcher;
        $this->configurationManager = $configurationManager;
    }

    private function PerformRecursiveContainerStart(string $id, bool $pullContainer = true) : void {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        foreach($container->GetDependsOn() as $dependency) {
            $this->PerformRecursiveContainerStart($dependency, $pullContainer);
        }

        if ($id === 'nextcloud-aio-database') {
            if ($this->dockerActionManager->GetDatabasecontainerExitCode() > 0) {
                $pullContainer = false;
                error_log('Not pulling the latest database image because the container was not correctly shut down.');
            }
        }
        $this->dockerActionManager->DeleteContainer($container);
        $this->dockerActionManager->CreateVolumes($container);
        if ($pullContainer) {
            $this->dockerActionManager->PullContainer($container);
        }
        $this->dockerActionManager->CreateContainer($container);
        $this->dockerActionManager->StartContainer($container);
        $this->dockerActionManager->ConnectContainerToNetwork($container);
    }

    public function GetLogs(Request $request, Response $response, $args) : Response
    {
        $id = $request->getQueryParams()['id'];
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

    public function StartBackupContainerBackup(Request $request, Response $response, $args) : Response {
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

    public function StartBackupContainerCheck(Request $request, Response $response, $args) : Response {
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

    public function StartBackupContainerRestore(Request $request, Response $response, $args) : Response {
        $config = $this->configurationManager->GetConfig();
        $config['backup-mode'] = 'restore';
        $config['selected-restore-time'] = $request->getParsedBody()['selected_restore_time'];
        $this->configurationManager->WriteConfig($config);

        $id = self::TOP_CONTAINER;
        $this->PerformRecursiveContainerStop($id);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);

        return $response->withStatus(201)->withHeader('Location', '/');
    }

    public function StartBackupContainerTest(Request $request, Response $response, $args) : Response {
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

    public function StartContainer(Request $request, Response $response, $args) : Response
    {
        $uri = $request->getUri();
        $host = $uri->getHost();
        $port = $uri->getPort();
        if ($port === 8000) {
            error_log('The AIO_URL-port was discovered to be 8000 which is not expected. It is now set to 443.');
            $port = 443;
        }

        $config = $this->configurationManager->GetConfig();
        // set AIO_URL
        $config['AIO_URL'] = $host . ':' . $port;
        // set wasStartButtonClicked
        $config['wasStartButtonClicked'] = 1;
        $this->configurationManager->WriteConfig($config);

        // Start container
        $this->startTopContainer(true);

        return $response->withStatus(201)->withHeader('Location', '/');
    }

    public function startTopContainer(bool $pullContainer) : void {
        $config = $this->configurationManager->GetConfig();
        // set AIO_TOKEN
        $config['AIO_TOKEN'] = bin2hex(random_bytes(24));
        $this->configurationManager->WriteConfig($config);

        // Stop domaincheck since apache would not be able to start otherwise
        $this->StopDomaincheckContainer();

        $id = self::TOP_CONTAINER;

        $this->PerformRecursiveContainerStart($id, $pullContainer);
    }

    public function StartWatchtowerContainer(Request $request, Response $response, $args) : Response {
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
        foreach($container->GetDependsOn() as $dependency) {
            $this->PerformRecursiveContainerStop($dependency);
        }

        // Disconnecting is not needed. This also allows to start the containers manually via docker-cli
        //$this->dockerActionManager->DisconnectContainerFromNetwork($container);
        $this->dockerActionManager->StopContainer($container);
    }

    public function StopContainer(Request $request, Response $response, $args) : Response
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
        if ($apacheContainer->GetRunningState() instanceof RunningState) {
            return;
        // Don't start if domaincheck is already running
        } elseif ($domaincheckContainer->GetRunningState() instanceof RunningState) {
            $domaincheckWasStarted = apcu_fetch($cacheKey);
            // Start domaincheck again when 10 minutes are over by not returning here
            if($domaincheckWasStarted !== false && is_string($domaincheckWasStarted)) {
                return;
            }
        }

        $this->StopDomaincheckContainer();
        $this->PerformRecursiveContainerStart($id);

        // Cache the start for 10 minutes
        apcu_add($cacheKey, '1', 600);
    }

    private function StopDomaincheckContainer() : void
    {
        $id = 'nextcloud-aio-domaincheck';
        $this->PerformRecursiveContainerStop($id);
    }
}
