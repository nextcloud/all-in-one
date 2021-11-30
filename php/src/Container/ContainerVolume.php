<?php

namespace AIO\Container;

class ContainerVolume {
    public string $name;
    public string $mountPoint;
    public bool $isWritable;

    public function __construct(
        string $name,
        string $mountPoint,
        bool $isWritable
    ) {
        $this->name = $name;
        $this->mountPoint = $mountPoint;
        $this->isWritable = $isWritable;
    }
}
