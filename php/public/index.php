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
const AIO_MAX_EXECUTION_TIME   = '7200';    // (2h) Supports slow networks and long operations
const AIO_COOKIE_LIFETIME      = '0';       // Session cookie expires with browser close
const AIO_SESSION_MAX_LIFETIME = '86400';   // (24h) Maximum admin session length
const AIO_LOG_ERRORS_MAX_LEN   = '0';       // Log full-length errors for troubleshooting
const AIO_TWIG_CACHE_PATH      = false;     // e.g., __DIR__ . '/../var/twig-cache'
const AIO_DISPLAY_ERRORS       = false;	    // Do not directly expose errors to site visitors

//-------------------------------------------------
// PHP Settings
//-------------------------------------------------
ini_set('memory_limit',            AIO_MEMORY_LIMIT);
ini_set('max_execution_time',      AIO_MAX_EXECUTION_TIME); // Prevent timeouts on slow networks
ini_set('session.cookie_lifetime', AIO_COOKIE_LIFETIME); // Auto-logout on browser close
ini_set('session.gc_maxlifetime',  AIO_SESSION_MAX_LIFETIME); // 24h session duration
ini_set('log_errors_max_len',      AIO_LOG_ERRORS_MAX_LEN); // Full error logs

//-------------------------------------------------
// Dependency Injection
//-------------------------------------------------
$container = \AIO\DependencyInjection::GetContainer();
AppFactory::setContainer($container);

// Session directory depends on application config
$dataConst = $container->get(\AIO\Data\DataConst::class);
ini_set('session.save_path', $dataConst->GetSessionDirectory());

//-------------------------------------------------
// Application Creation and Core Middleware
//-------------------------------------------------
$app = AppFactory::create();
$responseFactory = $app->getResponseFactory();

// Register CSRF middleware (container-only)
$container->set(Guard::class, function () use ($responseFactory): Guard {
    $guard = new Guard($responseFactory);
    $guard->setPersistentTokenMode(true);
    return $guard;
});

session_start();

// Activate CSRF middleware for all routes
$app->add(Guard::class);

// Setup and activate Twig middleware
$twig = Twig::create(__DIR__ . '/../templates/',
    [ 'cache' => AIO_TWIG_CACHE_PATH ]
);
$app->add(TwigMiddleware::create($app, $twig));

// Add CSRF extension to Twig so templates can access CSRF tokens
$twig->addExtension(new \AIO\Twig\CsrfExtension($container->get(Guard::class)));

// Establish and activate authentication middleware for all routes
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

    // Ensure master container is attached to the required Docker network
    $dockerActionManager->ConnectMasterContainerToNetwork();
    // Ensure the domaincheck container is started for domain configuration validation
    $dockerController->StartDomaincheckContainer();

    // URL parameters    
    $params = $request->getQueryParams();
    $bypassMastercontainerUpdate = isset($params['bypass_mastercontainer_update']);
    $bypassContainerUpdate = isset($params['bypass_container_update']);
    $skipDomainValidation = isset($params['skip_domain_validation']);

    return $view->render($response, 'containers.twig', [
        // ---- Basic Settings ----
        'domain' => $configurationManager->domain,
        'timezone' => $configurationManager->timezone,
        'current_channel' => $dockerActionManager->GetCurrentChannel(),
        'apache_port' => $configurationManager->apachePort,
        'talk_port' => $configurationManager->talkPort,
        // ---- Container Management ----
        'containers' => (new \AIO\ContainerDefinitionFetcher($container->get(\AIO\Data\ConfigurationManager::class), $container))->FetchDefinition(),
        'was_start_button_clicked' => $configurationManager->wasStartButtonClicked,
        'has_update_available' => $dockerActionManager->isAnyUpdateAvailable(),
        'is_mastercontainer_update_available' => ( $bypassMastercontainerUpdate ? false : $dockerActionManager->IsMastercontainerUpdateAvailable() ),
        'automatic_updates' => $configurationManager->areAutomaticUpdatesEnabled(),
        // ---- Nextcloud Settings ----
        'nextcloud_password' => $configurationManager->getAndGenerateSecret('NEXTCLOUD_PASSWORD'),
        'nextcloud_datadir' => $configurationManager->nextcloudDatadirMount,
        'nextcloud_mount' => $configurationManager->nextcloudMount,
        // ---- PHP Configuration ----
        'nextcloud_upload_limit' => $configurationManager->nextcloudUploadLimit,
        'nextcloud_max_time' => $configurationManager->nextcloudMaxTime,
        'nextcloud_memory_limit' => $configurationManager->nextcloudMemoryLimit,
        // ---- Optional Component Toggles ----
        'is_clamav_enabled' => $configurationManager->isClamavEnabled,
        'is_onlyoffice_enabled' => $configurationManager->isOnlyofficeEnabled,
        'is_collabora_enabled' => $configurationManager->isCollaboraEnabled,
        'is_talk_enabled' => $configurationManager->isTalkEnabled,
        'is_imaginary_enabled' => $configurationManager->isImaginaryEnabled,
        'is_fulltextsearch_enabled' => $configurationManager->isFulltextsearchEnabled,
        'is_dri_device_enabled' => $configurationManager->nextcloudEnableDriDevice,
        'is_nvidia_gpu_enabled' => $configurationManager->enableNvidiaGpu,
        'is_talk_recording_enabled' => $configurationManager->isTalkRecordingEnabled,
        'is_docker_socket_proxy_enabled' => $configurationManager->isDockerSocketProxyEnabled,
        'is_whiteboard_enabled' => $configurationManager->isWhiteboardEnabled,
        'is_backup_section_enabled' => !$configurationManager->disableBackupSection,
        // ---- Collabora Component ----
        'collabora_dictionaries' => $configurationManager->collaboraDictionaries,
        'collabora_additional_options' => $configurationManager->collaboraAdditionalOptions,
        // ---- Backup Component ----
        'borg_backup_host_location' => $configurationManager->borgBackupHostLocation,
        'borg_remote_repo' => $configurationManager->borgRemoteRepo,
        'borg_public_key' => $configurationManager->getBorgPublicKey(),
        'borgbackup_password' => $configurationManager->getAndGenerateSecret('BORGBACKUP_PASSWORD'),
        'has_backup_run_once' => $configurationManager->hasBackupRunOnce(),
        'is_backup_container_running' => $dockerActionManager->isBackupContainerRunning(),
        'backup_exit_code' => $dockerActionManager->GetBackupcontainerExitCode(),
        'is_instance_restore_attempt' => $configurationManager->instanceRestoreAttempt,
        'borg_backup_mode' => $configurationManager->backupMode,
        'last_backup_time' => $configurationManager->getLastBackupTime(),
        'backup_times' => $configurationManager->getBackupTimes(),
        'borg_restore_password' => $configurationManager->borgRestorePassword,
        'daily_backup_time' => $configurationManager->getDailyBackupTime(),
        'is_daily_backup_running' => $configurationManager->isDailyBackupRunning(),
        'additional_backup_directories' => $configurationManager->getAdditionalBackupDirectoriesString(),
        // ---- Community Containers ----
        'community_containers' => $configurationManager->listAvailableCommunityContainers(),
        'community_containers_enabled' => $configurationManager->aioCommunityContainers,
        // ---- Admin Overrides ----
        'skip_domain_validation' => $configurationManager->shouldDomainValidationBeSkipped($skipDomainValidation),
        'bypass_container_update' => $bypassContainerUpdate,
    ]);
});

// Login
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

    if (!$setup->CanBeInstalled()) {
        return $view->render($response, 'already-installed.twig');
    }
    return $view->render($response, 'setup.twig', [
            'password' => $setup->Setup(),
    ]);
});

//-------------------------------------------------
// Root Redirector
//-------------------------------------------------
$app->get('/', function (Request $request, Response $response, array $args) use ($container): Response {
    /** @var \AIO\Auth\AuthManager $authManager */
    $authManager = $container->get(\AIO\Auth\AuthManager::class);
    /** @var \AIO\Data\Setup $setup */
    $setup = $container->get(\AIO\Data\Setup::class);

    if ($setup->CanBeInstalled()) {
        return $response->withHeader('Location', 'setup')->withStatus(302);
    }
    if ($authManager->IsAuthenticated()) {
        return $response->withHeader('Location', 'containers')->withStatus(302);
    }
    return $response->withHeader('Location', 'login')->withStatus(302);
});

//-------------------------------------------------
// Error Middleware
//-------------------------------------------------

// TODO: Figure out why the default plain text renderer is being used by logging
// TODO: Change logging to not generate stack traces for 404s
// TODO: Change logging to log the path
$errorMiddleware = $app->addErrorMiddleware(AIO_DISPLAY_ERRORS, true, true);

$app->run();
