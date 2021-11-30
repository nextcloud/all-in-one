<?php

namespace AIO\Container;

class ContainerVolumes {
    /** @var ContainerVolume[] */
    private array $volumes = [];

    public function AddVolume(ContainerVolume $volume) {
        $this->volumes[] = $volume;
    }

    /**
     * @return ContainerVolume[]
     */
    public function GetVolumes() : array {
        return $this->volumes;
    }
}
