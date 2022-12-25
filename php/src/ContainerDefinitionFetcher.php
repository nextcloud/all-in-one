<?php

namespace AIO;

use AIO\Container\Container;
use AIO\Container\ContainerEnvironmentVariables;
use AIO\Container\ContainerPort;
use AIO\Container\ContainerPorts;
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
        foreach ($data['services'] as $entry) {
            if ($entry['container_name'] === 'nextcloud-aio-clamav') {
                if (!$this->configurationManager->isClamavEnabled()) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-onlyoffice') {
                if (!$this->configurationManager->isOnlyofficeEnabled()) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-collabora') {
                if (!$this->configurationManager->isCollaboraEnabled()) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-talk') {
                if (!$this->configurationManager->isTalkEnabled()) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-imaginary') {
                if (!$this->configurationManager->isImaginaryEnabled()) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-fulltextsearch') {
                if (!$this->configurationManager->isFulltextsearchEnabled()) {
                    continue;
                }
            }

            $ports = new ContainerPorts();
            foreach ($entry['ports'] as $value) {
                $ports->AddPort(
                    new ContainerPort(
                        $value['port'],
                        $value['ip_binding'],
                        $value['protocol']
                    )
                );
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
                } elseif ($value['name'] === '%NEXTCLOUD_TRUSTED_CACERTS_DIR%') {
                    $value['name'] = $this->configurationManager->GetTrustedCacertsDir();
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
            foreach ($entry['depends_on'] as $value) {
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
                } elseif ($value === 'nextcloud-aio-imaginary') {
                    if (!$this->configurationManager->isImaginaryEnabled()) {
                        continue;
                    }
                } elseif ($value === 'nextcloud-aio-fulltextsearch') {
                    if (!$this->configurationManager->isFulltextsearchEnabled()) {
                        continue;
                    }
                }
                $dependsOn[] = $value;
            }
            
            $variables = new ContainerEnvironmentVariables();
            foreach ($entry['environment'] as $value) {
                $variables->AddVariable($value);
            }

            $containers[] = new Container(
                $entry['container_name'],
                $entry['display_name'],
                $entry['image'],
                $entry['restart'],
                $entry['stop_grace_period'],
                $ports,
                $entry['internal_port'],
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
