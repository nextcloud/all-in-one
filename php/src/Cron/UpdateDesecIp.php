<?php
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
