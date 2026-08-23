<?php
declare(strict_types=1);

// increase memory limit to 2GB
ini_set('memory_limit', '2048M');

use DI\Container;

require __DIR__ . '/../../vendor/autoload.php';

$container = \AIO\DependencyInjection::GetContainer();

/** @var \AIO\Data\ConfigurationManager $configurationManager */
$configurationManager = $container->get(\AIO\Data\ConfigurationManager::class);

// Nag the admins to set up a second factor as long as none is configured.
if (!$configurationManager->isTwoFactorAuthEnabled()) {
    /** @var \AIO\Docker\DockerActionManager $dockerActionManager */
    $dockerActionManager = $container->get(\AIO\Docker\DockerActionManager::class);
    /** @var \AIO\ContainerDefinitionFetcher $containerDefinitionFetcher */
    $containerDefinitionFetcher = $container->get(\AIO\ContainerDefinitionFetcher::class);
    $id = 'nextcloud-aio-nextcloud';
    $nextcloudContainer = $containerDefinitionFetcher->GetContainerById($id);
    $dockerActionManager->sendNotification($nextcloudContainer, 'Two-factor authentication is not enabled!', 'We strongly recommend protecting the Nextcloud AIO interface with a second factor. You can enable two-factor authentication in the AIO interface.');
}
