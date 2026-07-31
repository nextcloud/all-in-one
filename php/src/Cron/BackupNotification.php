<?php
declare(strict_types=1);

// increase memory limit to 2GB
ini_set('memory_limit', '2048M');

use DI\Container;
use AIO\Data\DataConst;

require __DIR__ . '/../../vendor/autoload.php';

$container = \AIO\DependencyInjection::GetContainer();

/** @var \AIO\Docker\DockerActionManager $dockerActionManager */
$dockerActionManager = $container->get(\AIO\Docker\DockerActionManager::class);
/** @var \AIO\ContainerDefinitionFetcher $containerDefinitionFetcher */
$containerDefinitionFetcher = $container->get(\AIO\ContainerDefinitionFetcher::class);

$id = 'nextcloud-aio-nextcloud';
$nextcloudContainer = $containerDefinitionFetcher->GetContainerById($id);

$backupExitCode = $dockerActionManager->GetBackupcontainerExitCode();

if ($backupExitCode === 0) {
    if (getenv('SEND_SUCCESS_NOTIFICATIONS') === "0") {
        error_log("Daily backup successful! Only logging successful backup and not sending backup notification since that has been disabled! You can get further info by looking at the backup logs in the AIO interface.");
    } else {
        $dockerActionManager->sendNotification($nextcloudContainer, 'Daily backup successful!', 'You can get further info by looking at the backup logs in the AIO interface.');
    }
}

if ($backupExitCode > 0) {
    $dockerActionManager->sendNotification($nextcloudContainer, 'Daily backup failed!', 'You can get further info by looking at the backup logs in the AIO interface.');
}

// Check for container image pull failures and send notification
$failuresFile = DataConst::GetImagePullFailuresFile();
if (is_file($failuresFile)) {
    $failureMessage = file_get_contents($failuresFile);
    if ($failureMessage !== false && $failureMessage !== '') {
        $dockerActionManager->sendNotification($nextcloudContainer, 'Container image update failed!', $failureMessage . ' The containers were started with the previously available images. Please check the docker host for issues (e.g. registry connectivity, disk space).');
    }
    unlink($failuresFile);
}
