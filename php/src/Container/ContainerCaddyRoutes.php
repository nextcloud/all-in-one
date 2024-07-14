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
}
