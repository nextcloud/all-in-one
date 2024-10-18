<?php

namespace AIO\Container;

enum UpdateState: string {
    case Outdated = 'outdated';
    case Latest = 'latest';
}
