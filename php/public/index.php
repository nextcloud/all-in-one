<?php
declare(strict_types=1);

/**
 * Entry point/Bootstrapper for the Nextcloud All-in-One Web UI & API.
 * Initializes DI container, configures PHP, registers routes & middleware.
 */

require __DIR__ . '/../vendor/autoload.php';

use DI\Container;
use Slim\Csrf\Guard;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

//-------------------------------------------------
// Configuration Constants
//-------------------------------------------------
const AIO_MEMORY_LIMIT         = '2048M';
const AIO_MAX_EXECUTION_TIME   = '7200';    // (2h) in case of a very slow internet connection
const AIO_SESSION_MAX_LIFETIME = '86400';   // (24h)
const AIO_COOKIE_LIFETIME      = '0';       // Auto logout on browser close
const AIO_LOG_ERRORS_MAX_LEN   = '0';       // Log whole log messages

const AIO_TWIG_CACHE_PATH      = false;     // e.g., __DIR__ . '/../var/twig-cache'
const AIO_DISPLAY_ERRORS       = false;

//-------------------------------------------------
// PHP Settings
//-------------------------------------------------
ini_set('memory_limit',            AIO_MEMORY_LIMIT);
ini_set('max_execution_time',      AIO_MAX_EXECUTION_TIME);
ini_set('session.cookie_lifetime', AIO_COOKIE_LIFETIME);
ini_set('session.gc_maxlifetime',  AIO_SESSION_MAX_LIFETIME);
ini_set('log_errors_max_len',      AIO_LOG_ERRORS_MAX_LEN);

//-------------------------------------------------
// Dependency Injection
//-------------------------------------------------
$container = \AIO\DependencyInjection::GetContainer();
AppFactory::setContainer($container);

// Session directory depends on application config:
$dataConst = $container->get(\AIO\Data\DataConst::class);
ini_set('session.save_path', $dataConst->GetSessionDirectory());

//-------------------------------------------------
// Application Creation and Core Middleware
//-------------------------------------------------
$app = AppFactory::create();
$responseFactory = $app->getResponseFactory();

$container->set(Guard::class, function () use ($responseFactory): Guard {
    $guard = new Guard($responseFactory);
    $guard->setPersistentTokenMode(true);
    return $guard;
});

// Register Middleware To Be Executed On All Routes
session_start();
$app->add(Guard::class);

$twig = Twig::create(__DIR__ . '/../templates/',
    [ 'cache' => AIO_TWIG_CACHE_PATH ]
);
$app->add(TwigMiddleware::create($app, $twig));
$twig->addExtension(new \AIO\Twig\CsrfExtension($container->get(Guard::class)));

// Auth Middleware
$app->add(new \AIO\Middleware\AuthMiddleware($container->get(\AIO\Auth\AuthManager::class)));

//-------------------------------------------------
// API Routes
//-------------------------------------------------
$app->group('/api/docker', function (RouteCollectorProxy $group): void {
    // Docker Container management
    $group->post('/watchtower', AIO\Controller\DockerController::class . ':StartWatchtowerContainer');
    $group->get('/getwatchtower', AIO\Controller\DockerController::class . ':StartWatchtowerContainer');
    $group->post('/start', AIO\Controller\DockerController::class . ':StartContainer');
    $group->post('/stop', AIO\Controller\DockerController::class . ':StopContainer');
    $group->get('/logs', AIO\Controller\DockerController::class . ':GetLogs');
    // Backups
    $group->post('/backup', AIO\Controller\DockerController::class . ':StartBackupContainerBackup');
    $group->post('/backup-check', AIO\Controller\DockerController::class . ':StartBackupContainerCheck');
    $group->post('/backup-list', AIO\Controller\DockerController::class . ':StartBackupContainerList');
    $group->post('/backup-check-repair', AIO\Controller\DockerController::class . ':StartBackupContainerCheckRepair');
    $group->post('/backup-test', AIO\Controller\DockerController::class . ':StartBackupContainerTest');
    $group->post('/restore', AIO\Controller\DockerController::class . ':StartBackupContainerRestore');
});

// Auth-related
$app->group('/api/auth', function (RouteCollectorProxy $group): void {
    $group->post('/login', AIO\Controller\LoginController::class . ':TryLogin');
    $group->get('/getlogin', AIO\Controller\LoginController::class . ':GetTryLogin');
    $group->post('/logout', AIO\Controller\LoginController::class . ':Logout');
});

// Configuration endpoints
$app->post('/api/configuration', AIO\Controller\ConfigurationController::class . ':SetConfig');

//-------------------------------------------------
// Views Routes
//-------------------------------------------------

// Containers
$app->get('/containers', function (Request $request, Response $response, array $args) use ($container): Response {
    $view = Twig::fromRequest($request);
    $view->addExtension(new \AIO\Twig\ClassExtension());
    /** @var \AIO\Data\ConfigurationManager $configurationManager */
    $configurationManager = $container->get(\AIO\Data\ConfigurationManager::class);
    /** @var \AIO\Docker\DockerActionManager $dockerActionManager */
    $dockerActionManager = $container->get(\AIO\Docker\DockerActionManager::class);
    /** @var \AIO\Controller\DockerController $dockerController */
    $dockerController = $container->get(\AIO\Controller\DockerController::class);
    $dockerActionManager->ConnectMasterContainerToNetwork();
    $dockerController->StartDomaincheckContainer();

    // Check if bypass_mastercontainer_update is provided on the URL, a special developer mode to bypass a mastercontainer update and use local image.
    $params = $request->getQueryParams();
    $bypass_mastercontainer_update = isset($params['bypass_mastercontainer_update']);
    $bypass_container_update = isset($params['bypass_container_update']);
    $skip_domain_validation = isset($params['skip_domain_validation']);

    return $view->render($response, 'containers.twig', [
        'domain' => $configurationManager->domain,
        'apache_port' => $configurationManager->apachePort,
        'borg_backup_host_location' => $configurationManager->borgBackupHostLocation,
        'borg_remote_repo' => $configurationManager->borgRemoteRepo,
        'borg_public_key' => $configurationManager->getBorgPublicKey(),
        'nextcloud_password' => $configurationManager->getAndGenerateSecret('NEXTCLOUD_PASSWORD'),
        'containers' => (new \AIO\ContainerDefinitionFetcher($container->get(\AIO\Data\ConfigurationManager::class), $container))->FetchDefinition(),
        'borgbackup_password' => $configurationManager->getAndGenerateSecret('BORGBACKUP_PASSWORD'),
        'is_mastercontainer_update_available' => ( $bypass_mastercontainer_update ? false : $dockerActionManager->IsMastercontainerUpdateAvailable() ),
        'has_backup_run_once' => $configurationManager->hasBackupRunOnce(),
        'is_backup_container_running' => $dockerActionManager->isBackupContainerRunning(),
        'backup_exit_code' => $dockerActionManager->GetBackupcontainerExitCode(),
        'is_instance_restore_attempt' => $configurationManager->instanceRestoreAttempt,
        'borg_backup_mode' => $configurationManager->backupMode,
        'was_start_button_clicked' => $configurationManager->wasStartButtonClicked,
        'has_update_available' => $dockerActionManager->isAnyUpdateAvailable(),
        'last_backup_time' => $configurationManager->getLastBackupTime(),
        'backup_times' => $configurationManager->getBackupTimes(),
        'current_channel' => $dockerActionManager->GetCurrentChannel(),
        'is_clamav_enabled' => $configurationManager->isClamavEnabled,
        'is_onlyoffice_enabled' => $configurationManager->isOnlyofficeEnabled,
        'is_collabora_enabled' => $configurationManager->isCollaboraEnabled,
        'is_talk_enabled' => $configurationManager->isTalkEnabled,
        'borg_restore_password' => $configurationManager->borgRestorePassword,
        'daily_backup_time' => $configurationManager->getDailyBackupTime(),
        'is_daily_backup_running' => $configurationManager->isDailyBackupRunning(),
        'timezone' => $configurationManager->timezone,
        'skip_domain_validation' => $configurationManager->shouldDomainValidationBeSkipped($skip_domain_validation),
        'talk_port' => $configurationManager->talkPort,
        'collabora_dictionaries' => $configurationManager->collaboraDictionaries,
        'collabora_additional_options' => $configurationManager->collaboraAdditionalOptions,
        'automatic_updates' => $configurationManager->areAutomaticUpdatesEnabled(),
        'is_backup_section_enabled' => !$configurationManager->disableBackupSection,
        'is_imaginary_enabled' => $configurationManager->isImaginaryEnabled,
        'is_fulltextsearch_enabled' => $configurationManager->isFulltextsearchEnabled,
        'additional_backup_directories' => $configurationManager->getAdditionalBackupDirectoriesString(),
        'nextcloud_datadir' => $configurationManager->nextcloudDatadirMount,
        'nextcloud_mount' => $configurationManager->nextcloudMount,
        'nextcloud_upload_limit' => $configurationManager->nextcloudUploadLimit,
        'nextcloud_max_time' => $configurationManager->nextcloudMaxTime,
        'nextcloud_memory_limit' => $configurationManager->nextcloudMemoryLimit,
        'is_dri_device_enabled' => $configurationManager->nextcloudEnableDriDevice,
        'is_nvidia_gpu_enabled' => $configurationManager->enableNvidiaGpu,
        'is_talk_recording_enabled' => $configurationManager->isTalkRecordingEnabled,
        'is_docker_socket_proxy_enabled' => $configurationManager->isDockerSocketProxyEnabled,
        'is_whiteboard_enabled' => $configurationManager->isWhiteboardEnabled,
        'community_containers' => $configurationManager->listAvailableCommunityContainers(),
        'community_containers_enabled' => $configurationManager->aioCommunityContainers,
        'bypass_container_update' => $bypass_container_update,
    ]);
})->setName('profile');
$app->get('/login', function (Request $request, Response $response, array $args) use ($container) {
$app->get('/login', function (Request $request, Response $response, array $args) use ($container): Response {
    $view = Twig::fromRequest($request);
    /** @var \AIO\Docker\DockerActionManager $dockerActionManager */
    $dockerActionManager = $container->get(\AIO\Docker\DockerActionManager::class);
    return $view->render($response, 'login.twig', [
        'is_login_allowed' => $dockerActionManager->isLoginAllowed(),
    ]);
});

// Setup
$app->get('/setup', function (Request $request, Response $response, array $args) use ($container): Response {
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

//-------------------------------------------------
// Root Redirector
//-------------------------------------------------
$app->get('/', function (Request $request, Response $response, array $args) use ($container): Response {
    /** @var \AIO\Auth\AuthManager $authManager */
    $authManager = $container->get(\AIO\Auth\AuthManager::class);

    /** @var \AIO\Data\Setup $setup */
    $setup = $container->get(\AIO\Data\Setup::class);
    if($setup->CanBeInstalled()) {
        return $response
            ->withHeader('Location', 'setup')
            ->withStatus(302);
    }

    if($authManager->IsAuthenticated()) {
        return $response
            ->withHeader('Location', 'containers')
            ->withStatus(302);
    } else {
        return $response
            ->withHeader('Location', 'login')
            ->withStatus(302);
    }
});

//-------------------------------------------------
// Error Middleware
//-------------------------------------------------

// TODO: Figure out why the default plain text renderer is being used by logging
// TODO: Change logging to not generate stack traces for 404s
// TODO: Change logging to log the path
$errorMiddleware = $app->addErrorMiddleware(AIO_DISPLAY_ERRORS, true, true);

$app->run();
