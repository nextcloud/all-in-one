<?php
declare(strict_types=1);

namespace AIO\Container;

enum VersionState: string {
    case Different = 'different';
    case Equal = 'equal';
}
