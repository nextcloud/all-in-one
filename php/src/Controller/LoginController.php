<?php

namespace AIO\Controller;

use AIO\Auth\AuthManager;
use AIO\Container\Container;
use AIO\ContainerDefinitionFetcher;
use AIO\Docker\DockerActionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LoginController
{
    private AuthManager $authManager;
    private DockerActionManager $dockerActionManager;

    public function __construct(AuthManager $authManager, DockerActionManager $dockerActionManager) {
        $this->authManager = $authManager;
        $this->dockerActionManager = $dockerActionManager;
    }

    public function TryLogin(Request $request, Response $response, $args) : Response {
        if (!$this->dockerActionManager->isLoginAllowed()) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }
        $password = $request->getParsedBody()['password'] ?? '';
        if($this->authManager->CheckCredentials($password)) {
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function GetTryLogin(Request $request, Response $response, $args) : Response {
        $token = $request->getQueryParams()['token'] ?? '';
        if($this->authManager->CheckToken($token)) {
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function Logout(Request $request, Response $response, $args) : Response
    {
        $this->authManager->SetAuthState(false);
        return $response
            ->withHeader('Location', '/')
            ->withStatus(302);
    }
}
