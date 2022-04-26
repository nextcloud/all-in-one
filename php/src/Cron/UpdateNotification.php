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
    $dockerActionManger->sendNotification($nextcloudContainer, 'Mastercontainer update available!', 'Please open your AIO interface to update it. If you do not want to do it manually each time, you can enable the daily backup feature from the AIO interface which also automatically updates the mastercontainer.');
}

if ($isAnyUpdateAvailable === true) {
    $dockerActionManger->sendNotification($nextcloudContainer, 'Container updates available!', 'Please open your AIO interface to update them. If you do not want to do it manually each time, you can enable the daily backup feature from the AIO interface which also automatically updates your containers and your Nextcloud apps.');
}
