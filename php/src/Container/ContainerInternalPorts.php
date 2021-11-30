<?php

namespace AIO\Container;

class ContainerInternalPorts {
    /** @var string[] */
    private array $internalPorts = [];

    public function AddInternalPort(string $internalPort) : void {
        $this->internalPorts[] = $internalPort;
    }

    /**
     * @return string[]
     */
    public function GetInternalPorts() : array {
        return $this->internalPorts;
    }
}
