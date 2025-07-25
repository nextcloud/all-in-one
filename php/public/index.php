<?php
declare(strict_types=1);

// increase memory limit to 2GB
ini_set('memory_limit', '2048M');

// set max execution time to 2h just in case of a very slow internet connection
ini_set('max_execution_time', '7200');

// Log whole log messages
ini_set('log_errors_max_len', '0');

use DI\Container;
use Slim\Csrf\Guard;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

$container = \AIO\DependencyInjection::GetContainer();
$dataConst = $container->get(\AIO\Data\DataConst::class);
ini_set('session.save_path', $dataConst->GetSessionDirectory());

// Auto logout on browser close
ini_set('session.cookie_lifetime', '0');

# Keep session for 24h max
ini_set('session.gc_maxlifetime', '86400');

// Create app
AppFactory::setContainer($container);
$app = AppFactory::create();
$responseFactory = $app->getResponseFactory();

// Register Middleware On Container
$container->set(Guard::class, function () use ($responseFactory) {
    $guard = new Guard($responseFactory);
    $guard->setPersistentTokenMode(true);
    return $guard;
});

// Register Middleware To Be Executed On All Routes
session_start();
$app->add(Guard::class);

// Create Twig
$twig = Twig::create(__DIR__ . '/../templates/', ['cache' => false]);
$app->add(TwigMiddleware::create($app, $twig));
$twig->addExtension(new \AIO\Twig\CsrfExtension($container->get(Guard::class)));

// Auth Middleware
$app->add(new \AIO\Middleware\AuthMiddleware($container->get(\AIO\Auth\AuthManager::class)));

// API
$app->post('/api/docker/watchtower', AIO\Controller\DockerController::class . ':StartWatchtowerContainer');
$app->get('/api/docker/getwatchtower', AIO\Controller\DockerController::class . ':StartWatchtowerContainer');
$app->post('/api/docker/start', AIO\Controller\DockerController::class . ':StartContainer');
$app->post('/api/docker/backup', AIO\Controller\DockerController::class . ':StartBackupContainerBackup');
$app->post('/api/docker/backup-check', AIO\Controller\DockerController::class . ':StartBackupContainerCheck');
$app->post('/api/docker/backup-check-repair', AIO\Controller\DockerController::class . ':StartBackupContainerCheckRepair');
$app->post('/api/docker/backup-test', AIO\Controller\DockerController::class . ':StartBackupContainerTest');
$app->post('/api/docker/restore', AIO\Controller\DockerController::class . ':StartBackupContainerRestore');
$app->post('/api/docker/stop', AIO\Controller\DockerController::class . ':StopContainer');
$app->get('/api/docker/logs', AIO\Controller\DockerController::class . ':GetLogs');
$app->post('/api/auth/login', AIO\Controller\LoginController::class . ':TryLogin');
$app->get('/api/auth/getlogin', AIO\Controller\LoginController::class . ':GetTryLogin');
$app->post('/api/auth/logout', AIO\Controller\LoginController::class . ':Logout');
$app->post('/api/configuration', \AIO\Controller\ConfigurationController::class . ':SetConfig');

// Views
$app->get('/containers', function (Request $request, Response $response, array $args) use ($container) {
    $view = Twig::fromRequest($request);
    $view->addExtension(new \AIO\Twig\ClassExtension());
    /** @var \AIO\Data\ConfigurationManager $configurationManager */
    $configurationManager = $container->get(\AIO\Data\ConfigurationManager::class);
    /** @var \AIO\Docker\DockerActionManager $dockerActionManger */
    $dockerActionManger = $container->get(\AIO\Docker\DockerActionManager::class);
    /** @var \AIO\Controller\DockerController $dockerController */
    $dockerController = $container->get(\AIO\Controller\DockerController::class);
    $dockerActionManger->ConnectMasterContainerToNetwork();
    $dockerController->StartDomaincheckContainer();

    // Check if bypass_mastercontainer_update is provided on the URL, a special developer mode to bypass a mastercontainer update and use local image.
    $params = $request->getQueryParams();
    $bypass_mastercontainer_update = isset($params['bypass_mastercontainer_update']);

    return $view->render($response, 'containers.twig', [
        'domain' => $configurationManager->GetDomain(),
        'apache_port' => $configurationManager->GetApachePort(),
        'borg_backup_host_location' => $configurationManager->GetBorgBackupHostLocation(),
        'borg_remote_repo' => $configurationManager->GetBorgRemoteRepo(),
        'borg_remote_path' => $configurationManager->GetBorgRemotePath(),
        'borg_public_key' => $configurationManager->GetBorgPublicKey(),
        'nextcloud_password' => $configurationManager->GetAndGenerateSecret('NEXTCLOUD_PASSWORD'),
        'containers' => (new \AIO\ContainerDefinitionFetcher($container->get(\AIO\Data\ConfigurationManager::class), $container))->FetchDefinition(),
        'borgbackup_password' => $configurationManager->GetAndGenerateSecret('BORGBACKUP_PASSWORD'),
        'is_mastercontainer_update_available' => ( $bypass_mastercontainer_update ? false : $dockerActionManger->IsMastercontainerUpdateAvailable() ),
        'has_backup_run_once' => $configurationManager->hasBackupRunOnce(),
        'is_backup_container_running' => $dockerActionManger->isBackupContainerRunning(),
        'backup_exit_code' => $dockerActionManger->GetBackupcontainerExitCode(),
        'is_instance_restore_attempt' => $configurationManager->isInstanceRestoreAttempt(),
        'borg_backup_mode' => $configurationManager->GetBorgBackupMode(),
        'was_start_button_clicked' => $configurationManager->wasStartButtonClicked(),
        'has_update_available' => $dockerActionManger->isAnyUpdateAvailable(),
        'last_backup_time' => $configurationManager->GetLastBackupTime(),
        'backup_times' => $configurationManager->GetBackupTimes(),
        'current_channel' => $dockerActionManger->GetCurrentChannel(),
        'is_clamav_enabled' => $configurationManager->isClamavEnabled(),
        'is_onlyoffice_enabled' => $configurationManager->isOnlyofficeEnabled(),
        'is_collabora_enabled' => $configurationManager->isCollaboraEnabled(),
        'is_talk_enabled' => $configurationManager->isTalkEnabled(),
        'borg_restore_password' => $configurationManager->GetBorgRestorePassword(),
        'daily_backup_time' => $configurationManager->GetDailyBackupTime(),
        'is_daily_backup_running' => $configurationManager->isDailyBackupRunning(),
        'timezone' => $configurationManager->GetTimezone(),
        'skip_domain_validation' => $configurationManager->shouldDomainValidationBeSkipped(),
        'talk_port' => $configurationManager->GetTalkPort(),
        'collabora_dictionaries' => $configurationManager->GetCollaboraDictionaries(),
        'collabora_additional_options' => $configurationManager->GetAdditionalCollaboraOptions(),
        'automatic_updates' => $configurationManager->areAutomaticUpdatesEnabled(),
        'is_backup_section_enabled' => $configurationManager->isBackupSectionEnabled(),
        'is_imaginary_enabled' => $configurationManager->isImaginaryEnabled(),
        'is_fulltextsearch_enabled' => $configurationManager->isFulltextsearchEnabled(),
        'additional_backup_directories' => $configurationManager->GetAdditionalBackupDirectoriesString(),
        'nextcloud_datadir' => $configurationManager->GetNextcloudDatadirMount(),
        'nextcloud_mount' => $configurationManager->GetNextcloudMount(),
        'nextcloud_upload_limit' => $configurationManager->GetNextcloudUploadLimit(),
        'nextcloud_max_time' => $configurationManager->GetNextcloudMaxTime(),
        'nextcloud_memory_limit' => $configurationManager->GetNextcloudMemoryLimit(),
        'is_dri_device_enabled' => $configurationManager->isDriDeviceEnabled(),
        'is_nvidia_gpu_enabled' => $configurationManager->isNvidiaGpuEnabled(),
        'is_talk_recording_enabled' => $configurationManager->isTalkRecordingEnabled(),
        'is_docker_socket_proxy_enabled' => $configurationManager->isDockerSocketProxyEnabled(),
        'is_whiteboard_enabled' => $configurationManager->isWhiteboardEnabled(),
        'community_containers' => $configurationManager->listAvailableCommunityContainers(),
        'community_containers_enabled' => $configurationManager->GetEnabledCommunityContainers(),
    ]);
})->setName('profile');
$app->get('/login', function (Request $request, Response $response, array $args) use ($container) {
    $view = Twig::fromRequest($request);
    /** @var \AIO\Docker\DockerActionManager $dockerActionManger */
    $dockerActionManger = $container->get(\AIO\Docker\DockerActionManager::class);
    return $view->render($response, 'login.twig', [
        'is_login_allowed' => $dockerActionManger->isLoginAllowed(),
    ]);
});
$app->get('/setup', function (Request $request, Response $response, array $args) use ($container) {
    $view = Twig::fromRequest($request);
    /** @var \AIO\Data\Setup $setup */
    $setup = $container->get(\AIO\Data\Setup::class);

    if(!$setup->CanBeInstalled()) {
        return $view->render(
            $response,
            'already-installed.twig'
        );
    }

    return $view->render(
        $response,
        'setup.twig',
        [
            'password' => $setup->Setup(),
        ]
    );
});

// Auth Redirector
$app->get('/', function (\Psr\Http\Message\RequestInterface $request, Response $response, array $args) use ($container) {
    /** @var \AIO\Auth\AuthManager $authManager */
    $authManager = $container->get(\AIO\Auth\AuthManager::class);

    /** @var \AIO\Data\Setup $setup */
    $setup = $container->get(\AIO\Data\Setup::class);
    if($setup->CanBeInstalled()) {
        return $response
            ->withHeader('Location', '/setup')
            ->withStatus(302);
    }

    if($authManager->IsAuthenticated()) {
        return $response
            ->withHeader('Location', '/containers')
            ->withStatus(302);
    } else {
        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }
});

$errorMiddleware = $app->addErrorMiddleware(false, true, true);

$app->run();
