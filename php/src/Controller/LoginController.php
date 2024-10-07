<?php

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
        if (!$this->dockerActionManager->isLoginAllowed()) {
            $response->getBody()->write("The login is blocked since Nextcloud is running.");
            return $response->withHeader('Location', '/')->withStatus(422);
        }
        $password = $request->getParsedBody()['password'] ?? '';
        if($this->authManager->CheckCredentials($password)) {
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '/')->withStatus(201);
        }

        $response->getBody()->write("The password is incorrect.");
        return $response->withHeader('Location', '/')->withStatus(422);
    }

    public function GetTryLogin(Request $request, Response $response, array $args) : Response {
        $token = $request->getQueryParams()['token'] ?? '';
        if($this->authManager->CheckToken($token)) {
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function Logout(Request $request, Response $response, array $args) : Response
    {
        $this->authManager->SetAuthState(false);
        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }
}
