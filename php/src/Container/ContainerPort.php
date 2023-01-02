<?php

namespace AIO\Container;

class ContainerPort {
    public string $port;
    public string $ipBinding;
    public string $protocol;

    public function __construct(
        string $port,
        string $ipBinding,
        string $protocol
    ) {
        $this->port = $port;
        $this->ipBinding = $ipBinding;
        $this->protocol = $protocol;
    }
}
