<?php

namespace AIO\Container;

use AIO\Container\State\IContainerState;
use AIO\Data\ConfigurationManager;
use AIO\Docker\DockerActionManager;
use AIO\ContainerDefinitionFetcher;

class Container {
    private string $identifier;
    private string $displayName;
    private string $containerName;
    private string $restartPolicy;
    private int $maxShutdownTime;
    private ContainerPorts $ports;
    private string $internalPorts;
    private ContainerVolumes $volumes;
    private ContainerEnvironmentVariables $containerEnvironmentVariables;
    /** @var string[] */
    private array $dependsOn;
    /** @var string[] */
    private array $secrets;
    /** @var string[] */
    private array $devices;
    /** @var string[] */
    private array $capAdd;
    private int $shmSize;
    private bool $apparmorUnconfined;
    /** @var string[] */
    private array $backupVolumes;
    private array $nextcloudExecCommands;
    private bool $readOnlyRootFs;
    private array $tmpfs;
    private bool $init;
    private string $imageTag;
    private AioVariables $aioVariables;
    private string $documentation;
    private DockerActionManager $dockerActionManager;

    public function __construct(
        string $identifier,
        string $displayName,
        string $containerName,
        string $restartPolicy,
        int $maxShutdownTime,
        ContainerPorts $ports,
        string $internalPorts,
        ContainerVolumes $volumes,
        ContainerEnvironmentVariables $containerEnvironmentVariables,
        array $dependsOn,
        array $secrets,
        array $devices,
        array $capAdd,
        int $shmSize,
        bool $apparmorUnconfined,
        array $backupVolumes,
        array $nextcloudExecCommands,
        bool $readOnlyRootFs,
        array $tmpfs,
        bool $init,
        string $imageTag,
        AioVariables $aioVariables,
        string $documentation,
        DockerActionManager $dockerActionManager
    ) {
        $this->identifier = $identifier;
        $this->displayName = $displayName;
        $this->containerName = $containerName;
        $this->restartPolicy = $restartPolicy;
        $this->maxShutdownTime = $maxShutdownTime;
        $this->ports = $ports;
        $this->internalPorts = $internalPorts;
        $this->volumes = $volumes;
        $this->containerEnvironmentVariables = $containerEnvironmentVariables;
        $this->dependsOn = $dependsOn;
        $this->secrets = $secrets;
        $this->devices = $devices;
        $this->capAdd = $capAdd;
        $this->shmSize = $shmSize;
        $this->apparmorUnconfined = $apparmorUnconfined;
        $this->backupVolumes = $backupVolumes;
        $this->nextcloudExecCommands = $nextcloudExecCommands;
        $this->readOnlyRootFs = $readOnlyRootFs;
        $this->tmpfs = $tmpfs;
        $this->init = $init;
        $this->imageTag = $imageTag;
        $this->aioVariables = $aioVariables;
        $this->documentation = $documentation;
        $this->dockerActionManager = $dockerActionManager;
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

    public function GetTmpfs() : array {
        return $this->tmpfs;
    }

    public function GetDevices() : array {
        return $this->devices;
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

    public function GetRunningState() : IContainerState {
        return $this->dockerActionManager->GetContainerRunningState($this);
    }

    public function GetRestartingState() : IContainerState {
        return $this->dockerActionManager->GetContainerRestartingState($this);
    }

    public function GetUpdateState() : IContainerState {
        return $this->dockerActionManager->GetContainerUpdateState($this);
    }

    public function GetStartingState() : IContainerState {
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
