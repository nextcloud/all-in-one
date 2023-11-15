<?php
declare(strict_types=1);

// increase memory limit to 2GB
ini_set('memory_limit', '2048M');

// Log whole log messages
ini_set('log_errors_max_len', '0');

use DI\Container;

require __DIR__ . '/../../vendor/autoload.php';

$container = \AIO\DependencyInjection::GetContainer();

/** @var \AIO\Controller\DockerController $dockerController */
$dockerController = $container->get(\AIO\Controller\DockerController::class);

// Start apache
$dockerController->startTopContainer(false);
