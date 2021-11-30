<?php

namespace AIO;

use AIO\Container\Container;
use AIO\Container\ContainerEnvironmentVariables;
use AIO\Container\ContainerPorts;
use AIO\Container\ContainerInternalPorts;
use AIO\Container\ContainerVolume;
use AIO\Container\ContainerVolumes;
use AIO\Container\State\RunningState;
use AIO\Data\ConfigurationManager;
use AIO\Data\DataConst;
use AIO\Docker\DockerActionManager;

class ContainerDefinitionFetcher
{
    private ConfigurationManager $configurationManager;
    private \DI\Container $container;

    public function __construct(
        ConfigurationManager $configurationManager,
        \DI\Container $container
    )
    {
        $this->configurationManager = $configurationManager;
        $this->container = $container;
    }

    public function GetContainerById(string $id): ?Container
    {
        $containers = $this->FetchDefinition();

        foreach ($containers as $container) {
            if ($container->GetIdentifier() === $id) {
                return $container;
            }
        }

        return null;
    }

    /**
     * @return array
     */
    private function GetDefinition(bool $latest): array
    {
        $data = json_decode(file_get_contents(__DIR__ . '/../containers.json'), true);

        $containers = [];
        foreach ($data['production'] as $entry) {
            $ports = new ContainerPorts();
            foreach ($entry['ports'] as $port) {
                $ports->AddPort($port);
            }

            $internalPorts = new ContainerInternalPorts();
            foreach ($entry['internalPorts'] as $internalPort) {
                $internalPorts->AddInternalPort($internalPort);
            }

            $volumes = new ContainerVolumes();
            foreach ($entry['volumes'] as $value) {
                if($value['name'] === '%BORGBACKUP_HOST_LOCATION%') {
                    $value['name'] = $this->configurationManager->GetBorgBackupHostLocation();
                    if($value['name'] === '') {
                        continue;
                    }
                }
                $volumes->AddVolume(
                    new ContainerVolume(
                        $value['name'],
                        $value['location'],
                        $value['writeable']
                    )
                );
            }

            $variables = new ContainerEnvironmentVariables();
            foreach ($entry['environmentVariables'] as $value) {
                $variables->AddVariable($value);
            }

            $containers[] = new Container(
                $entry['identifier'],
                $entry['displayName'],
                $entry['containerName'],
                $entry['restartPolicy'],
                $entry['maxShutdownTime'],
                $ports,
                $internalPorts,
                $volumes,
                $variables,
                $entry['dependsOn'],
                $entry['secrets'],
                $this->container->get(DockerActionManager::class)
            );
        }

        return $containers;
    }

    public function FetchDefinition(): array
    {
        if (!file_exists(DataConst::GetDataDirectory() . '/containers.json')) {
            $containers = $this->GetDefinition(true);
        } else {
            $containers = $this->GetDefinition(false);
        }

        $borgBackupMode = $this->configurationManager->GetBorgBackupMode();
        $fetchLatest = false;

        foreach ($containers as $container) {

            if ($container->GetIdentifier() === 'nextcloud-aio-borgbackup') {
                if ($container->GetRunningState() === RunningState::class) {
                    if ($borgBackupMode !== 'backup' && $borgBackupMode !== 'restore') {
                        $fetchLatest = true;
                    }
                } else {
                    $fetchLatest = true;
                }

            } elseif ($container->GetIdentifier() === 'nextcloud-aio-watchtower' && $container->GetRunningState() === RunningState::class) {
                return $containers;
            }
        }

        if ($fetchLatest === true) {
            $containers = $this->GetDefinition(true);
        }

        return $containers;
    }
}
