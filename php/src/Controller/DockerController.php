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

    private function PerformRecursiveContainerStart(string $id) : void {
        $container = $this->containerDefinitionFetcher->GetContainerById($id);

        foreach($container->GetDependsOn() as $dependency) {
            $this->PerformRecursiveContainerStart($dependency);
        }

        $this->dockerActionManager->DeleteContainer($container);
        $this->dockerActionManager->CreateVolumes($container);
        $this->dockerActionManager->PullContainer($container);
        $this->dockerActionManager->CreateContainer($container);
        $this->dockerActionManager->StartContainer($container);
        $this->dockerActionManager->ConnectContainerToNetwork($container);
    }

    public function GetLogs(Request $request, Response $response, $args) : Response
    {
        $id = $request->getQueryParams()['id'];
        $container = $this->containerDefinitionFetcher->GetContainerById($id);
        $logs = $this->dockerActionManager->GetLogs($container);
        $body = $response->getBody();
        $body->write($logs);

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8')
            ->withHeader('Content-Disposition', 'inline');
    }

    public function StartBackupContainerBackup(Request $request, Response $response, $args) : Response {
        $config = $this->configurationManager->GetConfig();
        $config['backup-mode'] = 'backup';
        $this->configurationManager->WriteConfig($config);

        $id = self::TOP_CONTAINER;
        $this->PerformRecursiveContainerStop($id);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);

        return $response->withStatus(201)->withHeader('Location', '/');
    }

    public function StartBackupContainerCheck(Request $request, Response $response, $args) : Response {
        $config = $this->configurationManager->GetConfig();
        $config['backup-mode'] = 'check';
        $this->configurationManager->WriteConfig($config);

        $id = 'nextcloud-aio-borgbackup';
        $this->PerformRecursiveContainerStart($id);

        return $response->withStatus(201)->withHeader('Location', '/');
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

    public function StartContainer(Request $request, Response $response, $args) : Response
    {
        $uri = $request->getUri();
        $host = $uri->getHost();
        $port = $uri->getPort();

        $config = $this->configurationManager->GetConfig();
        // set AIO_URL
        $config['AIO_URL'] = $host . ':' . $port;
        // set wasStartButtonClicked
        $config['wasStartButtonClicked'] = 1;
        // set AIO_TOKEN
        $config['AIO_TOKEN'] = bin2hex(random_bytes(24));
        $this->configurationManager->WriteConfig($config);

        // Stop domaincheck since apache would not be able to start otherwise
        $this->StopDomaincheckContainer();

        $id = self::TOP_CONTAINER;

        $this->PerformRecursiveContainerStart($id);
        return $response->withStatus(201)->withHeader('Location', '/');
    }

    public function StartWatchtowerContainer(Request $request, Response $response, $args) : Response {
        $id = 'nextcloud-aio-watchtower';

        $this->PerformRecursiveContainerStart($id);
        return $response->withStatus(201)->withHeader('Location', '/');
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

    public function StartDomaincheckContainer() : void
    {
        # Don't start if domain is already set
        if ($this->configurationManager->GetDomain() !== '' || $this->configurationManager->wasStartButtonClicked()) {
            return;
        }

        $id = 'nextcloud-aio-domaincheck';

        $domaincheckContainer = $this->containerDefinitionFetcher->GetContainerById($id);
        $apacheContainer = $this->containerDefinitionFetcher->GetContainerById(self::TOP_CONTAINER);
        // don't start if the domaincheck is already running
        if ($domaincheckContainer->GetRunningState() instanceof RunningState) {
            return;
        // don't start if apache is already running
        } elseif ($apacheContainer->GetRunningState() instanceof RunningState) {
            return;
        }

        $this->PerformRecursiveContainerStart($id);
    }

    private function StopDomaincheckContainer() : void
    {
        $id = 'nextcloud-aio-domaincheck';
        $this->PerformRecursiveContainerStop($id);
    }
}
