<?php


namespace AIO\Container;

class ContainerCaddyRoute {
    public string $route;
    public string $subDomain;
    public string $target_host;
    public string $target_port;
    public string $uriStripPrefix;

    public function __construct(
        string $route,
        string $subDomain,
        string $target_host,
        string $target_port,
        string $uriStripPrefix
    ) {
        $this->route = $route;
        $this->subDomain = $subDomain;
        $this->target_host = $target_host;
        $this->target_port = $target_port;
        $this->uriStripPrefix = $uriStripPrefix;
    }
}
