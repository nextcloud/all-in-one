<?php
declare(strict_types=1);

namespace AIO\Controller;

use AIO\Data\ConfigurationManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

readonly class DesecController {
    private const string DESEC_API_BASE = 'https://desec.io/api/v1';
    private const int MAX_SLUG_ATTEMPTS = 5;
    private const int SLUG_BYTES = 5; // bin2hex → 10-char slug
    private const string SLUG_PATTERN = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

    private Client $guzzleClient;

    public function __construct(
        private ConfigurationManager $configurationManager,
    ) {
        $this->guzzleClient = new Client([
            'timeout' => 15,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
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
                $token    = $this->registerDesecAccount($email, $password);
                $this->saveAccountCredentials($token, $password, $email);
            }

            $domain = $this->registerDesecDomain($token, $slug);
            $this->enableDesecContainers();
            $this->configurationManager->setDomain($domain, true);
            $this->updateIpIfDesecDomain();

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

    private function saveAccountCredentials(string $token, string $password, string $email): void {
        $this->configurationManager->startTransaction();
        $this->configurationManager->desecToken    = $token;
        $this->configurationManager->desecPassword = $password;
        $this->configurationManager->desecEmail    = $email;
        $this->configurationManager->commitTransaction();
    }

    private function enableDesecContainers(): void {
        $this->configurationManager->startTransaction();
        $enabled = array_values(array_filter(
            $this->configurationManager->aioCommunityContainers,
            fn(string $cc): bool => $cc !== '',
        ));
        if (!in_array('caddy', $enabled, true)) {
            $enabled[] = 'caddy';
        }
        if (!in_array('dnsmasq', $enabled, true)) {
            $enabled[] = 'dnsmasq';
        }
        $this->configurationManager->aioCommunityContainers = $enabled;
        $this->configurationManager->commitTransaction();
    }

    public function updateIpIfDesecDomain(): void {
        if (!$this->configurationManager->isDesecDomain()) {
            return;
        }

        $domain = $this->configurationManager->domain;
        $token  = $this->configurationManager->desecToken;

        try {
            $res    = $this->guzzleClient->get('https://update.dedyn.io/', [
                'query'   => ['hostname' => $domain],
                'headers' => ['Authorization' => 'Token ' . $token],
            ]);
            $status = trim($res->getBody()->getContents());
            if (str_starts_with($status, 'good') || str_starts_with($status, 'nochg')) {
                error_log('deSEC IP update for ' . $domain . ': ' . $status);
            } else {
                error_log('deSEC IP update for ' . $domain . ' returned unexpected response: ' . $status);
            }
        } catch (\Exception $e) {
            error_log('Could not update deSEC DNS record for ' . $domain . ': ' . $e->getMessage());
        }
    }

    /**
     * Creates a new deSEC account and returns the API token issued for it.
     *
     * @throws \Exception on network failure or an unexpected HTTP response
     */
    private function registerDesecAccount(string $email, string $password): string {
        try {
            $res = $this->guzzleClient->post(self::DESEC_API_BASE . '/auth/', [
                'json' => ['email' => $email, 'password' => $password],
            ]);
        } catch (TransferException $e) {
            throw new \Exception('Could not reach the deSEC API: ' . $e->getMessage());
        }

        $code = $res->getStatusCode();
        $body = $res->getBody()->getContents();

        if ($code === 400) {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data) && isset($data['email'])) {
                throw new \Exception(
                    'This email address is already registered at deSEC. '
                    . 'Please log in at https://desec.io to retrieve your token and set up your domain manually.',
                );
            }
            throw new \Exception('Registration at deSEC failed (HTTP 400): ' . $body);
        }

        if ($code !== 201) {
            throw new \Exception('Unexpected response from deSEC during account registration (HTTP ' . $code . '): ' . $body);
        }

        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['token']['token']) || !is_string($data['token']['token'])) {
            throw new \Exception('Could not extract the API token from the deSEC response. Please try again.');
        }

        return $data['token']['token'];
    }

    /**
     * Registers a dedyn.io domain for the authenticated account.
     * When $slug is empty a random 10-character slug is tried up to MAX_SLUG_ATTEMPTS times.
     *
     * @return string the fully-qualified domain name that was registered
     * @throws \Exception if the slug is taken, on network failure, or after exhausting random attempts
     */
    private function registerDesecDomain(string $token, string $slug): string {
        $random   = $slug === '';
        $attempts = $random ? self::MAX_SLUG_ATTEMPTS : 1;

        for ($i = 0; $i < $attempts; $i++) {
            $domain = ($random ? bin2hex(random_bytes(self::SLUG_BYTES)) : $slug) . ConfigurationManager::DEDYN_SUFFIX;

            try {
                $res = $this->guzzleClient->post(self::DESEC_API_BASE . '/domains/', [
                    'headers' => ['Authorization' => 'Token ' . $token],
                    'json'    => ['name' => $domain],
                ]);
            } catch (TransferException $e) {
                throw new \Exception('Could not reach the deSEC API: ' . $e->getMessage());
            }

            $code = $res->getStatusCode();

            if ($code === 201) {
                return $domain;
            }

            if ($code === 409) {
                if (!$random) {
                    throw new \Exception('"' . $domain . '" is already taken. Please choose a different subdomain and try again.');
                }
                continue;
            }

            throw new \Exception('Unexpected response from deSEC during domain registration (HTTP ' . $code . '): ' . $res->getBody()->getContents());
        }

        throw new \Exception('Could not register a free dedyn.io domain after ' . self::MAX_SLUG_ATTEMPTS . ' attempts. Please try again.');
    }
}
