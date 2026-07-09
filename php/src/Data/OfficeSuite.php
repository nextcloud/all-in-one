<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace AIO\Data;

enum OfficeSuite: string
{
    case Collabora  = 'collabora';
    case Onlyoffice = 'onlyoffice';
    case Eurooffice = 'eurooffice';
    case None       = '';
}
