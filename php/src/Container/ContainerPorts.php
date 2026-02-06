<?php
declare(strict_types=1);

namespace AIO\Container;

class ContainerPorts {
    /** @var ContainerPort[] */
    private array $ports = [];

    public function AddPort(ContainerPort $port) : void {
        $this->ports[] = $port;
    }

    /**
     * @return ContainerPort[]
     */
    public function GetPorts() : array {
        return $this->ports;
    }
}