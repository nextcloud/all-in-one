<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: 2021 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

namespace AIO\Container;

class ContainerPorts {
    /** @var ContainerPort[] */
    private array $ports = [];

    public function AddPort(ContainerPort $port) : void {
        $this->ports[] = $port;
    }

    /**
     * @return ContainerPort[]
     */
    public function GetPorts() : array {
        return $this->ports;
    }
}