<?php


namespace AIO\Container;

class ContainerCaddyRoute {
    public string $route;
    public string $subDomain;
    public string $target;
    public string $uriStripPrefix;

    public function __construct(
        string $route,
        string $subDomain,
        string $target,
        string $uriStripPrefix
    ) {
        $this->route = $route;
        $this->subDomain = $subDomain;
        $this->target = $target;
        $this->uriStripPrefix = $uriStripPrefix;
    }
}
