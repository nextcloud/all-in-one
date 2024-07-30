<?php


namespace AIO\Container;

class ContainerCaddyRoute {
    public string $route;
    public string $target;
    public bool $uriStripPrefix;

    public function __construct(
        string $route,
        string $target,
        string $uriStripPrefix
    ) {
        $this->route = $route;
        $this->target = $target;
        $this->uriStripPrefix = $uriStripPrefix === "true";
    }

    public function GetFormatedEnv() : string {
        return $this->target.",".$this->route.",".$this->uriStripPrefix?"1":"0";
    }
}
