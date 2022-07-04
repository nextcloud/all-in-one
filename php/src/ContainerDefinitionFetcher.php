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

    public function GetContainerById(string $id): Container
    {
        $containers = $this->FetchDefinition();

        foreach ($containers as $container) {
            if ($container->GetIdentifier() === $id) {
                return $container;
            }
        }

        throw new \Exception("The provided id " . $id . " was not found in the container definition.");
    }

    /**
     * @return array
     */
    private function GetDefinition(bool $latest): array
    {
        $data = json_decode(file_get_contents(__DIR__ . '/../containers.json'), true);

        $containers = [];
        foreach ($data['production'] as $entry) {
            if ($entry['identifier'] === 'nextcloud-aio-clamav') {
                if (!$this->configurationManager->isClamavEnabled()) {
                    continue;
                }
            } elseif ($entry['identifier'] === 'nextcloud-aio-onlyoffice') {
                if (!$this->configurationManager->isOnlyofficeEnabled()) {
                    continue;
                }
            } elseif ($entry['identifier'] === 'nextcloud-aio-collabora') {
                if (!$this->configurationManager->isCollaboraEnabled()) {
                    continue;
                }
            } elseif ($entry['identifier'] === 'nextcloud-aio-talk') {
                if (!$this->configurationManager->isTalkEnabled()) {
                    continue;
                }
            }

            $ports = new ContainerPorts();
            foreach ($entry['ports'] as $port) {
                if($port === '%APACHE_PORT%/tcp') {
                    $port = $this->configurationManager->GetApachePort() . '/tcp';
                } elseif($port === '%TALK_PORT%/tcp') {
                    $port = $this->configurationManager->GetTalkPort() . '/tcp';
                } elseif($port === '%TALK_PORT%/udp') {
                    $port = $this->configurationManager->GetTalkPort() . '/udp';
                }
                $ports->AddPort($port);
            }

            $internalPorts = new ContainerInternalPorts();
            foreach ($entry['internalPorts'] as $internalPort) {
                if($internalPort === '%APACHE_PORT%') {
                    $internalPort = $this->configurationManager->GetApachePort();
                } elseif($internalPort === '%TALK_PORT%') {
                    $internalPort = $this->configurationManager->GetTalkPort();
                }
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
                if($value['name'] === '%NEXTCLOUD_MOUNT%') {
                    $value['name'] = $this->configurationManager->GetNextcloudMount();
                    if($value['name'] === '') {
                        continue;
                    }
                } elseif ($value['name'] === '%NEXTCLOUD_DATADIR%') {
                    $value['name'] = $this->configurationManager->GetNextcloudDatadirMount();
                    if ($value['name'] === '') {
                        continue;
                    }
                } elseif ($value['name'] === '%DOCKER_SOCKET_PATH%') {
                    $value['name'] = $this->configurationManager->GetDockerSocketPath();
                    if($value['name'] === '') {
                        continue;
                    }
                }
                if ($value['location'] === '%NEXTCLOUD_MOUNT%') {
                    $value['location'] = $this->configurationManager->GetNextcloudMount();
                    if($value['location'] === '') {
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

            $dependsOn = [];
            foreach ($entry['dependsOn'] as $value) {
                if ($value === 'nextcloud-aio-clamav') {
                    if (!$this->configurationManager->isClamavEnabled()) {
                        continue;
                    }
                } elseif ($value === 'nextcloud-aio-onlyoffice') {
                    if (!$this->configurationManager->isOnlyofficeEnabled()) {
                        continue;
                    }
                } elseif ($value === 'nextcloud-aio-collabora') {
                    if (!$this->configurationManager->isCollaboraEnabled()) {
                        continue;
                    }
                } elseif ($value === 'nextcloud-aio-talk') {
                    if (!$this->configurationManager->isTalkEnabled()) {
                        continue;
                    }
                }
                $dependsOn[] = $value;
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
                $dependsOn,
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
