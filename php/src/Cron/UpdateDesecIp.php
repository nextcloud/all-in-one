<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

// increase memory limit to 2GB
ini_set('memory_limit', '2048M');

// Log whole log messages
ini_set('log_errors_max_len', '0');

require __DIR__ . '/../../vendor/autoload.php';

$container = \AIO\DependencyInjection::GetContainer();

/** @var \AIO\Desec\DesecManager $desecManager */
$desecManager = $container->get(\AIO\Desec\DesecManager::class);

$desecManager->updateIpIfDesecDomain();
