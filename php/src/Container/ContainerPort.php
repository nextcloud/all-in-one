<?php

namespace AIO\Container;

class ContainerPort {
    public function __construct(
        public string $port,
        public string $ipBinding,
        public string $protocol
    ) {
    }
}
