<?php

namespace AIO\Container;

class ContainerVolume {
    public function __construct(
        public string $name,
        public string $mountPoint,
        public bool $isWritable
    ) {
    }
}
