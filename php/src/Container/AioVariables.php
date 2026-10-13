<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

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
