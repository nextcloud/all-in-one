<?php

namespace AIO\Container;

class ContainerPort {
    public string $port;
    public string $ipBinding;
    public bool $protocol;

    public function __construct(
        string $port,
        string $ipBinding,
        bool $protocol
    ) {
        $this->port = $port;
        $this->ipBinding = $ipBinding;
        $this->protocol = $protocol;
    }
}
