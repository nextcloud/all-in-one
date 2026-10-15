<?php
declare(strict_types=1);

// increase memory limit to 2GB
ini_set('memory_limit', '2048M');

use DI\Container;

require __DIR__ . '/../../vendor/autoload.php';

$container = \AIO\DependencyInjection::GetContainer();

/** @var \AIO\Docker\DockerActionManager $dockerActionManager */
$dockerActionManager = $container->get(\AIO\Docker\DockerActionManager::class);

// Exit code 0: the mastercontainer and every enabled container are running and their images
// match the registry. Exit code 1: an update is available, a container is not running or a
// lookup failed. Any other exit code (e.g. a PHP error) must not be read as "up to date" either.
try {
    if ($dockerActionManager->IsEverythingRunningAndUpToDate()) {
        echo "Everything is running and up to date.\n";
        exit(0);
    }
} catch (\Throwable $e) {
    error_log('Could not check for updates: ' . $e->getMessage());
}
echo "Updates are available, a container is not running or the check failed.\n";
exit(1);
