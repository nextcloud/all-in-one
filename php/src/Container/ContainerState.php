<?php

namespace AIO\Container;

enum ContainerState {
    case DoesNotExist;
    case Restarting;
    case Healthy;
    case Starting;
    case Stopped;
    case Unhealthy;

    public function isStopped(): bool {
        return $this == self::Stopped;
    }

    public function isStarting(): bool {
        return $this == self::Starting;
    }

    public function isRestarting(): bool {
        return $this == self::Restarting;
    }

    public function isHealthy(): bool {
        return $this == self::Healthy;
    }

    public function isUnhealthy(): bool {
        return $this == self::Unhealthy;
    }

    public function isRunning(): bool {
        return $this->isHealthy() || $this->isUnhealthy() || $this->isStarting() || $this->isRestarting();
    }
}
