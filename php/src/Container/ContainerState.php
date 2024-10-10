<?php

namespace AIO\Container;

enum ContainerState: string {
    case DoesNotExist = 'does_not_exist';
    case Restarting = 'restarting';
    case Running = 'running';
    case Starting = 'starting';
    case Stopped = 'stopped';
    case Unhealthy = 'unhealthy';
}
