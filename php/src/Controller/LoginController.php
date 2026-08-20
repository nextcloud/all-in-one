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
        $totp = $request->getParsedBody()['totp'] ?? '';

        // Second-factor step of the token-based auto-login: a token was stashed in
        // the session by GetTryLogin, so it — not a password — is the first factor.
        if ($this->authManager->hasPendingToken()) {
            $token = $this->authManager->getPendingToken();
            if ($this->authenticate($this->authManager->CheckToken($token), $totp)) {
                // Remove the token only now that login has fully succeeded. Keeping
                // it on failure lets the user retry — a TOTP code that rolled over
                // between rendering and submitting must not kill the whole flow.
                $this->authManager->clearPendingToken();
                return $response->withHeader('Location', '.')->withStatus(201);
            }
            $response->getBody()->write("Login failed. Please check your two-factor authentication code and try again.");
            return $response->withHeader('Location', '.')->withStatus(422);
        }

        if (!$this->dockerActionManager->isLoginAllowed()) {
            $response->getBody()->write("The login is blocked since Nextcloud is running.");
            return $response->withHeader('Location', '.')->withStatus(422);
        }
        $password = $request->getParsedBody()['password'] ?? '';

        if ($this->authenticate($this->authManager->CheckCredentials($password), $totp)) {
            return $response->withHeader('Location', '.')->withStatus(201);
        }

        // One generic message that does not reveal which of the two factors failed.
        $response->getBody()->write("Login failed. Please check your credentials and, if enabled, your two-factor authentication code.");
        return $response->withHeader('Location', '.')->withStatus(422);
    }

    public function GetTryLogin(Request $request, Response $response, array $args) : Response {
        $token = $request->getQueryParams()['token'] ?? '';

        // Before validating the token, gate on 2FA: if it is enabled the token
        // alone is not enough. Stash it in the session (unvalidated) and hand off
        // to the /login flow, which asks for a code and then validates both.
        if ($this->authManager->isTwoFactorAuthEnabled()) {
            $this->authManager->storePendingToken($token);
            return $response->withHeader('Location', '../../login')->withStatus(302);
        }

        // No 2FA: validate the token as before.
        $this->authenticate($this->authManager->CheckToken($token), '');
        return $response->withHeader('Location', '../..')->withStatus(302);
    }

    /**
     * Shared login gate for both the password and the token flows. Succeeds only
     * when the primary credential AND the optional TOTP second factor both check
     * out. Both are evaluated without short-circuiting, so neither the outcome nor
     * the timing reveals which factor failed; the code is consumed only on full
     * success, so a mistyped password does not waste an otherwise-correct code.
     * Sleeps on failure as a simple bot-throttle. Returns whether login succeeded.
     */
    private function authenticate(bool $primaryCredentialOk, string $totpCode) : bool {
        [$twoFactorAuthOk, $twoFactorAuthCounter] = $this->authManager->verifyTwoFactorAuthCode($totpCode);

        if ($primaryCredentialOk && $twoFactorAuthOk) {
            $this->authManager->commitTwoFactorAuth($twoFactorAuthCounter);
            $this->authManager->SetAuthState(true);
            return true;
        }

        // Punish failed auth attempts with a delay, as a very simple means against bots.
        sleep(5);
        return false;
    }

    public function Logout(Request $request, Response $response, array $args) : Response
    {
        $this->authManager->SetAuthState(false);
        return $response
            ->withHeader('Location', '../..')
            ->withStatus(302);
    }
}
