<?php

namespace AIO\Container;

use AIO\Data\ConfigurationManager;
use AIO\Docker\DockerActionManager;
use AIO\ContainerDefinitionFetcher;
use JsonException;

readonly class Container {
    public function __construct(
        public string                        $identifier,
        public string                        $displayName,
        public string                        $containerName,
        public string                        $restartPolicy,
        public int                           $maxShutdownTime,
        public ContainerPorts                $ports,
        public string                        $internalPorts,
        public ContainerVolumes              $volumes,
        public ContainerEnvironmentVariables $containerEnvironmentVariables,
        /** @var string[] */
        public array                         $dependsOn,
        private string                        $uiSecret,
        /** @var string[] */
        public array                         $devices,
        public bool                          $enableNvidiaGpu,
        /** @var string[] */
        public array                         $capAdd,
        public int                           $shmSize,
        public bool                          $apparmorUnconfined,
        /** @var string[] */
        public array                         $backupVolumes,
        public array                         $nextcloudExecCommands,
        public bool                          $readOnlyRootFs,
        public array                         $tmpfs,
        public bool                          $init,
        public string                        $imageTag,
        public AioVariables                  $aioVariables,
        public string                        $documentation,
        private DockerActionManager           $dockerActionManager
    ) {
    }

    public function GetUiSecret() : string {
        return $this->dockerActionManager->GetAndGenerateSecretWrapper($this->uiSecret);
    }

    /**
     * @throws JsonException
     */
    public function GetRunningState() : ContainerState {
        return $this->dockerActionManager->GetContainerRunningState($this);
    }

    /**
     * @throws JsonException
     */
    public function GetRestartingState() : ContainerState {
        return $this->dockerActionManager->GetContainerRestartingState($this);
    }

    public function GetUpdateState() : VersionState {
        return $this->dockerActionManager->GetContainerUpdateState($this);
    }

    public function GetStartingState() : ContainerState {
        return $this->dockerActionManager->GetContainerStartingState($this);
    }
}
