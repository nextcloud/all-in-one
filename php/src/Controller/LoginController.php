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
    public function __construct(
        private AuthManager $authManager,
        private DockerActionManager $dockerActionManager,
    ) {
    }

    public function TryLogin(Request $request, Response $response, array $args) : Response {
        $isLoginAllowed = $this->dockerActionManager->isLoginAllowed();
        if (!$isLoginAllowed && !$this->authManager->IsLoginUnblockedForSession()) {
            // The form is stale (e.g. the unblocking expired): forms.js reloads the page on this status, which then shows the blocked login.
            return $response->withStatus(403);
        }
        $password = $request->getParsedBody()['password'] ?? '';
        if($this->authManager->CheckCredentials($password)) {
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '.')->withStatus(201);
        }

        if (!$isLoginAllowed) {
            // Only count failed attempts if the direct login got unblocked via the indirect login.
            $this->authManager->RegisterFailedLoginAttempt();
        }

        // Punish failed auth attempts with a delay, as a very simple means against bots.
        sleep(5);

        if (!$isLoginAllowed && !$this->authManager->IsLoginUnblockedForSession()) {
            // Too many failed attempts: forms.js reloads the page on this status, which then shows the blocked login.
            return $response->withStatus(403);
        }

        $response->getBody()->write("The password is incorrect.");
        return $response->withHeader('Location', '.')->withStatus(422);
    }

    public function GetTryLogin(Request $request, Response $response, array $args) : Response {
        $token = $request->getQueryParams()['token'] ?? '';
        if($this->authManager->CheckToken($token)) {
            $this->authManager->UnblockLoginForSession();
            return $response->withHeader('Location', '../../login')->withStatus(302);
        }

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
