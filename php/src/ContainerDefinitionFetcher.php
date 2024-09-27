<?php

namespace AIO;

use AIO\Container\AioVariables;
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
    private \DI\Container $container;

    public function __construct(
        \DI\Container $container
    )
    {
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
    private function GetDefinition(): array
    {
        $data = json_decode(file_get_contents(__DIR__ . '/../containers.json'), true);
        $config = ConfigurationManager::loadConfigFile();

        $additionalContainerNames = [];
        foreach ($config->aioCommunityContainers as $communityContainer) {
            if ($communityContainer !== '') {
                $path = DataConst::GetCommunityContainersDirectory() . '/' . $communityContainer . '/' . $communityContainer . '.json';
                $additionalData = json_decode(file_get_contents($path), true);
                $data = array_merge_recursive($data, $additionalData);
                if (isset($additionalData['aio_services_v1'][0]['display_name']) && $additionalData['aio_services_v1'][0]['display_name'] !== '') {
                    // Store container_name of community containers in variable for later
                    $additionalContainerNames[] = $additionalData['aio_services_v1'][0]['container_name'];
                }
            }
        }

        $containers = [];
        foreach ($data['aio_services_v1'] as $entry) {
            if ($entry['container_name'] === 'nextcloud-aio-clamav') {
                if (!$config->isClamavEnabled()) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-onlyoffice') {
                if (!$config->isOnlyofficeEnabled()) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-collabora') {
                if (!$config->isCollaboraEnabled()) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-talk') {
                if (!$config->talkEnabled) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-talk-recording') {
                if (!$config->isTalkRecordingEnabled()) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-imaginary') {
                if (!$config->imaginaryEnabled) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-fulltextsearch') {
                if (!$config->fulltextsearchEnabled) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-docker-socket-proxy') {
                if (!$config->dockerSocketProxyEnabled) {
                    continue;
                }
            } elseif ($entry['container_name'] === 'nextcloud-aio-whiteboard') {
                if (!$config->whiteboardEnabled) {
                    continue;
                }
            }

            $ports = new ContainerPorts();
            if (isset($entry['ports'])) {
                foreach ($entry['ports'] as $value) {
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
                        $value['source'] = $config->getBorgLocation();
                        if($value['source'] === '') {
                            continue;
                        }
                    }
                    if($value['source'] === '%NEXTCLOUD_MOUNT%') {
                        $value['source'] = $config->nextcloudMount;
                        if($value['source'] === '') {
                            continue;
                        }
                    } elseif ($value['source'] === '%NEXTCLOUD_DATADIR%') {
                        $value['source'] = $config->nextcloudDatadir;
                        if ($value['source'] === '') {
                            continue;
                        }
                    } elseif ($value['source'] === '%WATCHTOWER_DOCKER_SOCKET_PATH%') {
                        $value['source'] = $config->dockerSocketPath;
                        if($value['source'] === '') {
                            continue;
                        }
                    } elseif ($value['source'] === '%NEXTCLOUD_TRUSTED_CACERTS_DIR%') {
                        $value['source'] = $config->trustedCacertsDir;
                        if($value['source'] === '') {
                            continue;
                        }
                    }
                    if ($value['destination'] === '%NEXTCLOUD_MOUNT%') {
                        $value['destination'] = $config->nextcloudMount;
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
                $valueDependsOn = $entry['depends_on'];
                if ($entry['container_name'] === 'nextcloud-aio-apache') {
                    // Add community containers first and default ones last so that aio_variables works correctly
                    $valueDependsOnTemp = [];
                    foreach ($additionalContainerNames as $containerName) {
                        $valueDependsOnTemp[] = $containerName;
                    }
                    $valueDependsOn = array_merge_recursive($valueDependsOnTemp, $valueDependsOn);
                }
                foreach ($valueDependsOn as $value) {
                    if ($value === 'nextcloud-aio-clamav') {
                        if (!$config->isClamavEnabled()) {
                            continue;
                        }
                    } elseif ($value === 'nextcloud-aio-onlyoffice') {
                        if (!$config->isOnlyofficeEnabled()) {
                            continue;
                        }
                    } elseif ($value === 'nextcloud-aio-collabora') {
                        if (!$config->isCollaboraEnabled()) {
                            continue;
                        }
                    } elseif ($value === 'nextcloud-aio-talk') {
                        if (!$config->talkEnabled) {
                            continue;
                        }
                    } elseif ($value === 'nextcloud-aio-talk-recording') {
                        if (!$config->isTalkRecordingEnabled()) {
                            continue;
                        }
                    } elseif ($value === 'nextcloud-aio-imaginary') {
                        if (!$config->imaginaryEnabled) {
                            continue;
                        }
                    } elseif ($value === 'nextcloud-aio-fulltextsearch') {
                        if (!$config->fulltextsearchEnabled) {
                            continue;
                        }
                    } elseif ($value === 'nextcloud-aio-docker-socket-proxy') {
                        if (!$config->dockerSocketProxyEnabled) {
                            continue;
                        }
                    } elseif ($value === 'nextcloud-aio-whiteboard') {
                        if (!$config->whiteboardEnabled) {
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

            $aioVariables = new AioVariables();
            if (isset($entry['aio_variables'])) {
                foreach ($entry['aio_variables'] as $value) {
                    $aioVariables->AddVariable($value);
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

            $devices = [];
            if (isset($entry['devices'])) {
                $devices = $entry['devices'];
            }

            $capAdd = [];
            if (isset($entry['cap_add'])) {
                $capAdd = $entry['cap_add'];
            }

            $shmSize = -1;
            if (isset($entry['shm_size'])) {
                $shmSize = $entry['shm_size'];
            }

            $apparmorUnconfined = false;
            if (isset($entry['apparmor_unconfined'])) {
                $apparmorUnconfined = $entry['apparmor_unconfined'];
            }

            $backupVolumes = [];
            if (isset($entry['backup_volumes'])) {
                $backupVolumes = $entry['backup_volumes'];
            }

            $nextcloudExecCommands = [];
            if (isset($entry['nextcloud_exec_commands'])) {
                $nextcloudExecCommands = $entry['nextcloud_exec_commands'];
            }

            $readOnlyRootFs = false;
            if (isset($entry['read_only'])) {
                $readOnlyRootFs = $entry['read_only'];
            }

            $tmpfs = [];
            if (isset($entry['tmpfs'])) {
                $tmpfs = $entry['tmpfs'];
            }

            $init = true;
            if (isset($entry['init'])) {
                $init = $entry['init'];
            }

            $imageTag = '%AIO_CHANNEL%';
            if (isset($entry['image_tag'])) {
                $imageTag = $entry['image_tag'];
            }

            $documentation = '';
            if (isset($entry['documentation'])) {
                $documentation = $entry['documentation'];
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
                $devices,
                $capAdd,
                $shmSize,
                $apparmorUnconfined,
                $backupVolumes,
                $nextcloudExecCommands,
                $readOnlyRootFs,
                $tmpfs,
                $init,
                $imageTag,
                $aioVariables,
                $documentation,
                $this->container->get(DockerActionManager::class)
            );
        }

        return $containers;
    }

    public function FetchDefinition(): array
    {
        return $this->GetDefinition();
    }
}
