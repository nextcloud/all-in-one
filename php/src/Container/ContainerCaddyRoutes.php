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
        return implode(";", array_map(fn($caddyRoute) => $caddyRoute->GetFormatedEnv(), $this->caddyRoutes));
    }
}
