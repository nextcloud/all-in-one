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

$isMastercontainerUpdateAvailable = $dockerActionManger->IsMastercontainerUpdateAvailable();
$isAnyUpdateAvailable = $dockerActionManger->isAnyUpdateAvailable();

if ($isMastercontainerUpdateAvailable === true) {
    $dockerActionManger->sendNotification($nextcloudContainer, 'Mastercontainer update available!', 'Please open your management interface to update it.');
}

if ($isAnyUpdateAvailable === true) {
    $dockerActionManger->sendNotification($nextcloudContainer, 'Container updates available!', 'Please open your management interface to update them.');
}
