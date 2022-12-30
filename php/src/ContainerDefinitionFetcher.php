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
        foreach ($data['aio_services_v1'] as $entry) {
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
            if (isset($entry['ports'])) {
                foreach ($entry['ports'] as $value) {
                    if ($value['port_number'] === '%APACHE_PORT%') {
                        $value['port_number'] = $this->configurationManager->GetApachePort();
                    } elseif ($value['port_number'] === '%TALK_PORT%') {
                        $value['port_number'] = $this->configurationManager->GetTalkPort();
                    }

                    if ($value['ip_binding'] === '%APACHE_IP_BINDING%') {
                        $value['ip_binding'] = $this->configurationManager->GetApacheIPBinding();
                    }
                    
                    $ports->AddPort(
                        new ContainerPort(
                            $value['port_number'],
                            $value['ip_binding'],
                            $value['protocol']
                        )
                    );
                }
            }

            $volumes = new ContainerVolumes();
            if (isset($entry['volumes'])) {
                foreach ($entry['volumes'] as $value) {
                    if($value['source'] === '%BORGBACKUP_HOST_LOCATION%') {
                        $value['source'] = $this->configurationManager->GetBorgBackupHostLocation();
                        if($value['source'] === '') {
                            continue;
                        }
                    }
                    if($value['source'] === '%NEXTCLOUD_MOUNT%') {
                        $value['source'] = $this->configurationManager->GetNextcloudMount();
                        if($value['source'] === '') {
                            continue;
                        }
                    } elseif ($value['source'] === '%NEXTCLOUD_DATADIR%') {
                        $value['source'] = $this->configurationManager->GetNextcloudDatadirMount();
                        if ($value['source'] === '') {
                            continue;
                        }
                    } elseif ($value['source'] === '%DOCKER_SOCKET_PATH%') {
                        $value['source'] = $this->configurationManager->GetDockerSocketPath();
                        if($value['source'] === '') {
                            continue;
                        }
                    } elseif ($value['source'] === '%NEXTCLOUD_TRUSTED_CACERTS_DIR%') {
                        $value['source'] = $this->configurationManager->GetTrustedCacertsDir();
                        if($value['source'] === '') {
                            continue;
                        }
                    }
                    if ($value['destination'] === '%NEXTCLOUD_MOUNT%') {
                        $value['destination'] = $this->configurationManager->GetNextcloudMount();
                        if($value['destination'] === '') {
                            continue;
                        }
                    }
                    $volumes->AddVolume(
                        new ContainerVolume(
                            $value['source'],
                            $value['destination'],
                            $value['writeable']
                        )
                    );
                }
            }

            $dependsOn = [];
            if (isset($entry['depends_on'])) {
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
            }
            
            $variables = new ContainerEnvironmentVariables();
            if (isset($entry['environment'])) {
                foreach ($entry['environment'] as $value) {
                    $variables->AddVariable($value);
                }
            }

            $displayName = '';
            if (isset($entry['display_name'])) {
                $displayName = $entry['display_name'];
            }

            $restartPolicy = '';
            if (isset($entry['restart'])) {
                $restartPolicy = $entry['restart'];
            }

            $maxShutdownTime = 10;
            if (isset($entry['stop_grace_period'])) {
                $maxShutdownTime = $entry['stop_grace_period'];
            }

            $internalPort = '';
            if (isset($entry['internal_port'])) {
                $internalPort = $entry['internal_port'];
            }

            $secrets = [];
            if (isset($entry['secrets'])) {
                $secrets = $entry['secrets'];
            }

            $containers[] = new Container(
                $entry['container_name'],
                $displayName,
                $entry['image'],
                $restartPolicy,
                $maxShutdownTime,
                $ports,
                $internalPort,
                $volumes,
                $variables,
                $dependsOn,
                $secrets,
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
