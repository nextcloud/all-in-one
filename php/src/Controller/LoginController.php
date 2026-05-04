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
        //
        // REMOTE_ADDR is set by Caddy (the reverse proxy that sits in front of PHP-FPM inside
        // the mastercontainer), which passes the real client IP. In environments where an
        // additional upstream proxy forwards traffic to Caddy, operators should configure Caddy
        // with `trusted_proxies` so that REMOTE_ADDR reflects the actual client.
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        if ($ip === '') {
            // Refuse to fall back to a shared bucket when no IP is available.
            $response->getBody()->write("Unable to determine client IP. Login refused.");
            return $response->withStatus(403);
        }

        // Use HMAC so the cache keys are not predictable from IP addresses alone, preventing
        // enumeration of which IPs have attempted logins via APCu key inspection.
        // The HMAC key is persisted via the configuration manager so it survives cache clears
        // and container restarts, preserving rate-limit state across APCu evictions.
        $hmacKey = $this->dockerActionManager->GetAndGenerateSecretWrapper('RATE_LIMIT_HMAC_KEY');
        $rateLimitKey = 'login_attempts_' . hash_hmac('sha256', $ip, $hmacKey);
        $attempts = (int)(apcu_fetch($rateLimitKey) ?: 0);

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            // Return 429 immediately; the rate limit itself is sufficient protection.
            $response->getBody()->write("Too many failed login attempts. Please try again later.");
            return $response->withStatus(429);
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
