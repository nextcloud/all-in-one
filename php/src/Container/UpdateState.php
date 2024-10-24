<?php

namespace AIO\Container;

enum UpdateState {
    case Outdated;
    case Latest;

    public function isUpdatableAvailable(): bool {
        return $this == self::Outdated;
    }
}
