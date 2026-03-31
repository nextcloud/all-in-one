<?php
declare(strict_types=1);

namespace AIO\Controller;

use AIO\Auth\AuthManager;
use AIO\Container\Container;
use AIO\ContainerDefinitionFetcher;
use AIO\Docker\DockerActionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

readonly class LoginController {
    private const int MAX_LOGIN_ATTEMPTS_PER_TTL = 5;
    private const int LOGIN_COUNTER_TTL = 300;
    private const string RATE_LIMIT_CACHE_KEY = 'login_failed_attempts';

    public function __construct(
        private AuthManager $authManager,
        private DockerActionManager $dockerActionManager,
    ) {
    }

    private function getFailedLoginCount() : int {
        $count = apcu_fetch(self::RATE_LIMIT_CACHE_KEY);
        return $count !== false ? (int)$count : 0;
    }

    private function incrementFailedLoginCount() : void {
        if (!apcu_exists(self::RATE_LIMIT_CACHE_KEY)) {
            apcu_store(self::RATE_LIMIT_CACHE_KEY, 1, self::LOGIN_COUNTER_TTL);
        } else {
            apcu_inc(self::RATE_LIMIT_CACHE_KEY);
        }
    }

    private function resetFailedLoginCount() : void {
        apcu_delete(self::RATE_LIMIT_CACHE_KEY);
    }

    public function TryLogin(Request $request, Response $response, array $args) : Response {
        if (!$this->dockerActionManager->isLoginAllowed()) {
            $response->getBody()->write("The login is blocked since Nextcloud is running.");
            return $response->withHeader('Location', '.')->withStatus(422);
        }

        if ($this->getFailedLoginCount() >= self::MAX_LOGIN_ATTEMPTS_PER_TTL) {
            $response->getBody()->write("Too many failed login attempts. Please try again in some minutes.");
            return $response->withHeader('Location', '.')->withStatus(429);
        }

        $password = $request->getParsedBody()['password'] ?? '';
        if($this->authManager->CheckCredentials($password)) {
            $this->resetFailedLoginCount();
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '.')->withStatus(201);
        }

        $this->incrementFailedLoginCount();
        $response->getBody()->write("The password is incorrect.");
        return $response->withHeader('Location', '.')->withStatus(422);
    }

    public function GetTryLogin(Request $request, Response $response, array $args) : Response {
        $token = $request->getQueryParams()['token'] ?? '';
        if($this->authManager->CheckToken($token)) {
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '../..')->withStatus(302);
        }

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
