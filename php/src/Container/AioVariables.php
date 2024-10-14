<?php
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace AIO\Container;

class AioVariables {
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
