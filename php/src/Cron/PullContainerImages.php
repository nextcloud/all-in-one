<?php
declare(strict_types=1);

// increase memory limit to 2GB
ini_set('memory_limit', '2048M');

// Log whole log messages
ini_set('log_errors_max_len', '0');

use DI\Container;
use AIO\Data\DataConst;

require __DIR__ . '/../../vendor/autoload.php';

$container = \AIO\DependencyInjection::GetContainer();

/** @var \AIO\Controller\DockerController $dockerController */
$dockerController = $container->get(\AIO\Controller\DockerController::class);

// Remove any stale pull failure file from a previous run
$failuresFile = DataConst::GetImagePullFailuresFile();
if (is_file($failuresFile)) {
    unlink($failuresFile);
}

// Pull all containers
$failures = $dockerController->PullAllContainerImages();

if ($failures !== []) {
    $message = 'Failed to pull the following container images: ' . implode(', ', $failures);
    error_log($message);
    file_put_contents($failuresFile, $message);
    exit(1);
}
