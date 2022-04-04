<?php
declare(strict_types=1);

// increase memory limit to 2GB
ini_set('memory_limit', '2048M');

use DI\Container;

require __DIR__ . '/../../vendor/autoload.php';

$container = \AIO\DependencyInjection::GetContainer();

/** @var \AIO\Docker\DockerActionManager $dockerActionManger */
$dockerActionManger = $container->get(\AIO\Docker\DockerActionManager::class);
/** @var \AIO\ContainerDefinitionFetcher $containerDefinitionFetcher */
$containerDefinitionFetcher = $container->get(\AIO\ContainerDefinitionFetcher::class);

$id = 'nextcloud-aio-nextcloud';
$nextcloudContainer = $containerDefinitionFetcher->GetContainerById($id);

$backupExitCode = $dockerActionManger->GetBackupcontainerExitCode();

if ($backupExitCode === 0) {
    $dockerActionManger->sendNotification($nextcloudContainer, 'Daily backup successful!', 'You can get further info by looking at the backup logs in the AIO interface.');
}

if ($backupExitCode > 0) {
    $dockerActionManger->sendNotification($nextcloudContainer, 'Daily backup failed!', 'You can get further info by looking at the backup logs in the AIO interface.');
}
