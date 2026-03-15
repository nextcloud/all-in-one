<?php
declare(strict_types=1);

namespace AIO\Container;

class ContainerEnvironmentVariables {
    /** @var string[] */
    private array $variables = [];

    public function AddVariable(string $variable) : void {
        $this->variables[] = $variable;
    }

    /**
     * @return string[]
     */
    public function GetVariables() : array {
        return $this->variables;
    }
}
