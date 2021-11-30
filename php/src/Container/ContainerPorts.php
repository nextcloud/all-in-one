<?php

namespace AIO\Container;

class ContainerPorts {
    /** @var string[] */
    private array $ports = [];

    public function AddPort(string $port) : void {
        $this->ports[] = $port;
    }

    /**
     * @return string[]
     */
    public function GetPorts() : array {
        return $this->ports;
    }
}
