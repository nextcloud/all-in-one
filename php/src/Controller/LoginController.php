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

    /**
     * Maximum number of failed login attempts allowed within the rate-limit window.
     */
    private const int MAX_FAILED_ATTEMPTS = 10;

    /**
     * Duration in seconds during which failed attempts are counted (and for which a lockout lasts).
     */
    private const int RATE_LIMIT_WINDOW_SEC = 300;

    public function TryLogin(Request $request, Response $response, array $args) : Response {
        if (!$this->dockerActionManager->isLoginAllowed()) {
            $response->getBody()->write("The login is blocked since Nextcloud is running.");
            return $response->withHeader('Location', '.')->withStatus(422);
        }

        // Per-IP rate limiting: block after MAX_FAILED_ATTEMPTS failures within RATE_LIMIT_WINDOW_SEC.
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitKey = 'login_attempts_' . hash('sha256', $ip);
        $attempts = (int)(apcu_fetch($rateLimitKey) ?: 0);

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            // Keep a delay even when blocked so the 429 itself isn't a timing oracle.
            sleep(5);
            $response->getBody()->write("Too many failed login attempts. Please try again later.");
            return $response->withHeader('Location', '.')->withStatus(429);
        }

        $password = $request->getParsedBody()['password'] ?? '';
        if($this->authManager->CheckCredentials($password)) {
            // Clear the counter on success.
            apcu_delete($rateLimitKey);
            $this->authManager->SetAuthState(true);
            return $response->withHeader('Location', '.')->withStatus(201);
        }

        // Increment the failed-attempts counter (expires after RATE_LIMIT_WINDOW_SEC seconds).
        apcu_store($rateLimitKey, $attempts + 1, self::RATE_LIMIT_WINDOW_SEC);

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
            $response->getBody()->write(
                '<!DOCTYPE html>' .
                '<html lang="en">' .
                '<head><script src="../../clean-history.js"></script></head>' .
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
