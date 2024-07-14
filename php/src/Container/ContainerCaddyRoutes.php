<?php



namespace AIO\Container;

class ContainerCaddyRoutes {
    /** @var ContainerCaddyRoute[] */
    private array $caddyRoutes = [];

    public function AddCaddyRoute(ContainerCaddyRoute $caddyRoute) : void {
        $this->caddyRoutes[] = $caddyRoute;
    }

    /**
     * @return ContainerCaddyRoute[]
     */
    public function GetCaddyRoutes() : array {
        return $this->caddyRoutes;
    }

    public function GetFormatedEnv() : string {
        $caddyRouteBySubDomain = [];
        foreach ($this->caddyRoutes as $caddyRoute) {
            $subDomain = $caddyRoute->subDomain;
            if (!array_key_exists($subDomain, $caddyRouteBySubDomain)) {
                $caddyRouteBySubDomain[$subDomain] = [];
            }
            $caddyRouteBySubDomain[$subDomain][] =  $caddyRoute->route.",".$caddyRoute->uriStripPrefix.",".$caddyRoute->target_host.",".$caddyRoute->target_port ;
        }

        $subDomainGroups = [];
        foreach ($caddyRouteBySubDomain as $subDomain => $routes) {
            $subDomainGroups[] = $subDomain . "|" . implode(";", $routes);
        }

        return implode("@", $subDomainGroups);
    }
}
