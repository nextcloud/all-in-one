<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use AIO\Container\Container;
use AIO\ContainerDefinitionFetcher;
use AIO\Controller\DockerController;
use AIO\Data\ConfigurationManager;
use AIO\DependencyInjection;
use AIO\Desec\DesecManager;
use AIO\Docker\DockerActionManager;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

require __DIR__ . '/../vendor/autoload.php';

final readonly class TestImagePulls extends DockerActionManager {
    public function __construct(private Closure $respond, private bool $reachable = true, private int $databaseExitCode = 0) {}

    public function isRegistryReachable(Container $container): bool {
        return $this->reachable;
    }

    public function GetCurrentChannel(): string {
        return 'test';
    }

    public function GetDatabasecontainerExitCode(): int {
        return $this->databaseExitCode;
    }

    protected function sendHttpRequest(string $httpMethod, string $url, array $requestOptions = []): ResponseInterface {
        return ($this->respond)($httpMethod, $url);
    }
}

function check(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$services = DependencyInjection::GetContainer();
$definitions = $services->get(ContainerDefinitionFetcher::class);
$redis = $definitions->GetContainerById('nextcloud-aio-redis');
$failedImage = $redis->containerName . ':test';
$attempts = [];
$cached = true;
$failFor = 3;
$respond = static function (string $method, string $url) use ($failedImage, &$attempts, &$cached, &$failFor): Response {
    if ($method === 'GET') {
        if (!$cached) {
            throw new RuntimeException('No local image');
        }
        return new Response(200, [], '{}');
    }
    parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
    $image = $query['fromImage'];
    $attempts[$image] = ($attempts[$image] ?? 0) + 1;
    $event = $image === $failedImage && $attempts[$image] <= $failFor
        ? ['error' => 'layer download failed'] : ['status' => 'Downloaded'];
    return new Response(200, [], json_encode($event, JSON_THROW_ON_ERROR) . "\n");
};

$docker = new TestImagePulls($respond);
$controller = new DockerController($docker, $definitions, $services->get(ConfigurationManager::class), $services->get(DesecManager::class));
$errors = $controller->PullAllContainerImages();
check(is_array($errors) && array_keys($errors) === [$redis->identifier], 'Report only the image that failed with a local fallback.');
check(str_contains($errors[$redis->identifier], $failedImage) && str_contains($errors[$redis->identifier], 'layer download failed'), 'Report the image and the Docker error.');
check($attempts[$failedImage] === 3, 'Exhaust the retries before reporting a failure.');
$apache = $definitions->GetContainerById('nextcloud-aio-apache');
check(isset($attempts[$apache->containerName . ':test']), 'Continue pulling the other images.');

$attempts = [];
$failFor = 1;
check($controller->PullAllContainerImages() === [] && $attempts[$failedImage] === 2, 'A successful retry clears the failure.');

$cached = false;
$failFor = 3;
$attempts = [];
$fatalMessage = '';
try {
    $docker->PullImage($redis);
} catch (Exception $e) {
    $fatalMessage = $e->getMessage();
}
check(str_contains($fatalMessage, 'layer download failed'), 'Preserve the fatal Docker error when no local image is available.');
$attempts = [];
$errors = $controller->PullAllContainerImages();
check(isset($errors[$redis->identifier], $attempts[$apache->containerName . ':test']), 'A missing local image must not hide failures from the nightly report.');

$httpFailure = new TestImagePulls(static function (string $method, string $url): Response {
    if ($method === 'GET') {
        return new Response(200, [], '{}');
    }
    throw new RequestException('HTTP 503', new Request($method, $url), new Response(503, [], '{"message":"registry unavailable"}'));
});
check(str_ends_with((string)$httpFailure->PullImage($redis), ': registry unavailable'), 'Include the error reported by the Docker HTTP API.');

$noRequests = new TestImagePulls(static fn () => throw new LogicException('Unexpected Docker request'), reachable: false, databaseExitCode: 1);
check(str_contains((string)$noRequests->PullImage($redis), $failedImage), 'Report an unavailable registry for a requested pull.');
check($noRequests->PullImage($redis, false) === null, 'Explicitly skipped pulls are intentional.');
check($noRequests->PullImage($definitions->GetContainerById('nextcloud-aio-database')) === null, 'An unclean database shutdown intentionally skips the pull.');
echo "Image pull checks passed.\n";
