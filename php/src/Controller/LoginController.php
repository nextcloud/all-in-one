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
        if (!$this->dockerActionManager->isLoginAllowed()) {
            $response->getBody()->write("The login is blocked since Nextcloud is running.");
            return $response->withHeader('Location', '.')->withStatus(422);
        }
        $password = $request->getParsedBody()['password'] ?? '';
        if($this->authManager->CheckCredentials($password)) {
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '.')->withStatus(201);
        }

        // Punish failed auth attempts with a delay, as a very simple means against bots.
        sleep(5);

        $response->getBody()->write("The password is incorrect.");
        return $response->withHeader('Location', '.')->withStatus(422);
    }

    public function GetTryLogin(Request $request, Response $response, array $args) : Response {
        $token = $request->getQueryParams()['token'] ?? '';
        if($this->authManager->CheckToken($token)) {
            $this->authManager->SetAuthState(true);
            // Return a minimal HTML page that uses JavaScript to replace the browser's
            // current history entry (removing the token from it) before navigating to
            // the main AIO page.  This prevents the token from remaining in browser history.
            // The script is served from 'self'; same-origin scripts are already trusted under
            // the 'script-src-elem self' CSP directive, so no SRI hash is needed here.
            $response->getBody()->write(
                '<!DOCTYPE html>' .
                '<html lang="en">' .
                '<head><script src="../../clean-history.js" data-target="../../"></script></head>' .
                '<body></body>' .
                '</html>'
            );
            return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withStatus(200);
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
