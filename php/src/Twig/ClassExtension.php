<?php
// SPDX-FileCopyrightText: 2021 Nextcloud GmbH <https://nextcloud.com>
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace AIO\Twig;

use Slim\Views\TwigExtension;
use Twig\TwigFunction;

class ClassExtension extends TwigExtension
{
    #[\Override]
    public function getFunctions() : array
    {
        return array(
            new TwigFunction('class', array($this, 'getClassName')),
        );
    }

    public function getClassName(mixed $object) : ?string
    {
        if (!is_object($object)) {
            return null;
        }

        return get_class($object);
    }
}