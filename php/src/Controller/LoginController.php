<?php
declare(strict_types=1);

namespace AIO\Controller;

use AIO\Auth\AuthManager;
use AIO\Auth\RateLimiter;
use AIO\Container\Container;
use AIO\ContainerDefinitionFetcher;
use AIO\Docker\DockerActionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

readonly class LoginController {
    public function __construct(
        private AuthManager $authManager,
        private DockerActionManager $dockerActionManager,
        private RateLimiter $rateLimiter,
    ) {
    }

    public function TryLogin(Request $request, Response $response, array $args) : Response {
        $ip = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '');

        if ($this->rateLimiter->isLimitReached($ip)) {
            $response->getBody()->write("Too many failed login attempts. Please try again later.");
            return $response
                ->withHeader('Retry-After', (string)RateLimiter::WINDOW_SECONDS)
                ->withStatus(429);
        }

        if (!$this->dockerActionManager->isLoginAllowed()) {
            $response->getBody()->write("The login is blocked since Nextcloud is running.");
            return $response->withHeader('Location', '.')->withStatus(422);
        }
        $password = $request->getParsedBody()['password'] ?? '';
        if($this->authManager->CheckCredentials($password)) {
            $this->rateLimiter->resetAttempts($ip);
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '.')->withStatus(201);
        }

        $this->rateLimiter->recordFailedAttempt($ip);

        // Punish failed auth attempts with a delay, as a very simple means against bots.
        sleep(5);

        $response->getBody()->write("The password is incorrect.");
        return $response->withHeader('Location', '.')->withStatus(422);
    }

    public function GetTryLogin(Request $request, Response $response, array $args) : Response {
        $ip = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '');

        if ($this->rateLimiter->isLimitReached($ip)) {
            return $response
                ->withHeader('Retry-After', (string)RateLimiter::WINDOW_SECONDS)
                ->withStatus(429);
        }

        $token = $request->getQueryParams()['token'] ?? '';
        if($this->authManager->CheckToken($token)) {
            $this->rateLimiter->resetAttempts($ip);
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '../..')->withStatus(302);
        }

        $this->rateLimiter->recordFailedAttempt($ip);

        // Punish failed auth attempts with a delay, as a very simple means against bots.
        sleep(5);

        return $response->withHeader('Location', '../..')->withStatus(302);
    }

    public function Logout(Request $request, Response $response, array $args) : Response
    {
        $this->authManager->SetAuthState(false);
        return $response
            ->withHeader('Location', '../..')
            ->withStatus(302);
    }
}
