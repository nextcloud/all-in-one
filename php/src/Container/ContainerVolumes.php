<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: 2021 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

namespace AIO\Container;

class ContainerVolumes {
    /** @var ContainerVolume[] */
    private array $volumes = [];

    public function AddVolume(ContainerVolume $volume) : void {
        $this->volumes[] = $volume;
    }

    /**
     * @return ContainerVolume[]
     */
    public function GetVolumes() : array {
        return $this->volumes;
    }
}
