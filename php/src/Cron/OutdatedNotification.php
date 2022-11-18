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

$isNextcloudImageOutdated = $dockerActionManger->isNextcloudImageOutdated();

if ($isNextcloudImageOutdated === true) {
    $dockerActionManger->sendNotification($nextcloudContainer, 'AIO is outdated!', 'Please open the AIO interface or ask an administrator to update it. If you do not want to do it manually each time, you can enable the daily backup feature from the AIO interface which automatically updates all containers.', '/notify-all.sh');
}

