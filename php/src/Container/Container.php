<?php

namespace AIO\Container;

use AIO\Data\ConfigurationManager;
use AIO\Docker\DockerActionManager;
use AIO\ContainerDefinitionFetcher;

readonly class Container {
    public function __construct(
        private string                        $identifier,
        private string                        $displayName,
        private string                        $containerName,
        private string                        $restartPolicy,
        private int                           $maxShutdownTime,
        private ContainerPorts                $ports,
        private string                        $internalPorts,
        private ContainerVolumes              $volumes,
        private ContainerEnvironmentVariables $containerEnvironmentVariables,
        /** @var string[] */
        private array                         $dependsOn,
        /** @var string[] */
        private array                         $secrets,
        private string                        $uiSecret,
        /** @var string[] */
        private array                         $devices,
        private bool                          $enableNvidiaGpu,
        /** @var string[] */
        private array                         $capAdd,
        private int                           $shmSize,
        private bool                          $apparmorUnconfined,
        /** @var string[] */
        private array                         $backupVolumes,
        private array                         $nextcloudExecCommands,
        private bool                          $readOnlyRootFs,
        private array                         $tmpfs,
        private bool                          $init,
        private string                        $imageTag,
        private AioVariables                  $aioVariables,
        private string                        $documentation,
        private DockerActionManager           $dockerActionManager
    ) {
    }

    public function GetIdentifier() : string {
        return $this->identifier;
    }

    public function GetDisplayName() : string {
        return $this->displayName;
    }

    public function GetContainerName() : string {
        return $this->containerName;
    }

    public function GetRestartPolicy() : string {
        return $this->restartPolicy;
    }

    public function GetImageTag() : string {
        return $this->imageTag;
    }

    public function GetReadOnlySetting() : bool {
        return $this->readOnlyRootFs;
    }

    public function GetInit() : bool {
        return $this->init;
    }

    public function GetShmSize() : int {
        return $this->shmSize;
    }

    public function isApparmorUnconfined() : bool {
        return $this->apparmorUnconfined;
    }

    public function GetMaxShutdownTime() : int {
        return $this->maxShutdownTime;
    }

    public function GetSecrets() : array {
        return $this->secrets;
    }

    public function GetUiSecret() : string {
        return $this->dockerActionManager->GetAndGenerateSecretWrapper($this->uiSecret);
    }

    public function GetTmpfs() : array {
        return $this->tmpfs;
    }

    public function GetDevices() : array {
        return $this->devices;
    }

    public function isNvidiaGpuEnabled() : bool {
        return $this->enableNvidiaGpu;
    }

    public function GetCapAdds() : array {
        return $this->capAdd;
    }

    public function GetBackupVolumes() : array {
        return $this->backupVolumes;
    }

    public function GetPorts() : ContainerPorts {
        return $this->ports;
    }

    public function GetInternalPort() : string {
        return $this->internalPorts;
    }

    public function GetVolumes() : ContainerVolumes {
        return $this->volumes;
    }

    public function GetRunningState() : ContainerState {
        return $this->dockerActionManager->GetContainerRunningState($this);
    }

    public function GetRestartingState() : ContainerState {
        return $this->dockerActionManager->GetContainerRestartingState($this);
    }

    public function GetUpdateState() : VersionState {
        return $this->dockerActionManager->GetContainerUpdateState($this);
    }

    public function GetStartingState() : ContainerState {
        return $this->dockerActionManager->GetContainerStartingState($this);
    }

    /**
     * @return string[]
     */
    public function GetDependsOn() : array {
        return $this->dependsOn;
    }

    public function GetNextcloudExecCommands() : array {
        return $this->nextcloudExecCommands;
    }

    public function GetEnvironmentVariables() : ContainerEnvironmentVariables {
        return $this->containerEnvironmentVariables;
    }

    public function GetAioVariables() : AioVariables {
        return $this->aioVariables;
    }

    public function GetDocumentation() : string {
        return $this->documentation;
    }
}
