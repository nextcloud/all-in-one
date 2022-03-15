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
    private ContainerInternalPorts $internalPorts;
    private ContainerVolumes $volumes;
    private ContainerEnvironmentVariables $containerEnvironmentVariables;
    /** @var string[] */
    private array $dependsOn;
    /** @var string[] */
    private array $secrets;
    private DockerActionManager $dockerActionManager;

    public function __construct(
        string $identifier,
        string $displayName,
        string $containerName,
        string $restartPolicy,
        int $maxShutdownTime,
        ContainerPorts $ports,
        ContainerInternalPorts $internalPorts,
        ContainerVolumes $volumes,
        ContainerEnvironmentVariables $containerEnvironmentVariables,
        array $dependsOn,
        array $secrets,
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

    public function GetMaxShutdownTime() : int {
        return $this->maxShutdownTime;
    }

    public function GetSecrets() : array {
        return $this->secrets;
    }

    public function GetPorts() : ContainerPorts {
        return $this->ports;
    }

    public function GetInternalPorts() : ContainerInternalPorts {
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

    public function GetEnvironmentVariables() : ContainerEnvironmentVariables {
        return $this->containerEnvironmentVariables;
    }
}
