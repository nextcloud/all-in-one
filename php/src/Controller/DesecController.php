<?php
declare(strict_types=1);

namespace AIO\Controller;

use AIO\Data\ConfigurationManager;
use AIO\Desec\DesecManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

readonly class DesecController {
    private const string SLUG_PATTERN = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

    public function __construct(
        private ConfigurationManager $configurationManager,
        private DesecManager $desecManager,
    ) {
    }

    public function Register(Request $request, Response $response, array $args): Response {
        try {
            $this->validateNoDomainAlreadyConfigured();

            $accountAlreadyRegistered = $this->configurationManager->isDesecAccountRegistered();
            $token = $accountAlreadyRegistered
                ? $this->configurationManager->desecToken
                : null;

            $email = $accountAlreadyRegistered ? null : $this->getEmailFromRequest($request);
            $slug  = $this->getSlugFromRequest($request);

            if (!$accountAlreadyRegistered) {
                // 24 random bytes → 48-char hex password; satisfies deSEC's minimum length
                // and lets the user log in at desec.io if they ever need to.
                $password = bin2hex(random_bytes(24));
                $token    = $this->desecManager->registerAccount($email, $password);
                $this->desecManager->saveAccountCredentials($token, $password, $email);
            }

            $domain = $this->desecManager->registerDomain($token, $slug);
            $this->desecManager->enableDesecContainers();
            $this->configurationManager->setDomain($domain, true);
            $this->desecManager->updateIpIfDesecDomain();

            return $response->withStatus(201)->withHeader('Location', '.');
        } catch (\Exception $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
    }

    /** @throws \Exception if a domain is already configured */
    private function validateNoDomainAlreadyConfigured(): void {
        if ($this->configurationManager->domain !== '') {
            throw new \Exception('A domain is already configured. Reset the AIO instance first to register a new domain.');
        }
    }

    /**
     * Reads and validates the email address from the request body.
     *
     * @throws \Exception if the email is missing or syntactically invalid
     */
    private function getEmailFromRequest(Request $request): string {
        $email = trim((string)($request->getParsedBody()['desec_email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \Exception('Please provide a valid email address.');
        }
        return $email;
    }

    /**
     * Reads and validates the optional subdomain slug from the request body.
     * Returns an empty string when the user wants a randomly generated slug.
     *
     * @throws \Exception if the slug is present but does not match the allowed pattern
     */
    private function getSlugFromRequest(Request $request): string {
        $slug = trim((string)($request->getParsedBody()['desec_slug'] ?? ''));
        if ($slug !== '' && !preg_match(self::SLUG_PATTERN, $slug)) {
            throw new \Exception(
                'The desired subdomain must contain only lowercase letters, digits and hyphens, '
                . 'be between 1 and 63 characters long, and must not start or end with a hyphen.'
            );
        }
        return $slug;
    }
}
