<?php

namespace AIO\Container;

enum VersionState: string {
    case Different = 'different';
    case Equal = 'equal';
}
