<?php
declare(strict_types=1);

namespace AIO\Controller;

use AIO\Data\ConfigurationManager;
use AIO\Data\InvalidSettingConfigurationException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

readonly class DesecController {
    private const string DESEC_API_BASE = 'https://desec.io/api/v1';
    private const string DEDYN_SUFFIX = '.dedyn.io';
    private const int MAX_SLUG_ATTEMPTS = 5;
    private const int SLUG_BYTES = 5; // 10-char hex slug

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
        // Only allow registration when no domain is configured yet
        if ($this->configurationManager->domain !== '') {
            $response->getBody()->write('A domain is already configured. Reset the AIO instance first to register a new domain.');
            return $response->withStatus(422);
        }

        $email = trim((string)($request->getParsedBody()['desec_email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $response->getBody()->write('Please provide a valid email address.');
            return $response->withStatus(422);
        }

        try {
            // Register an account at deSEC and obtain an API token
            $password = bin2hex(random_bytes(24));
            $token = $this->registerDesecAccount($email, $password);

            // Register a free dedyn.io subdomain
            $domain = $this->registerDesecDomain($token);

            // Persist the credentials and auto-enable caddy as the reverse proxy
            $this->configurationManager->startTransaction();
            $this->configurationManager->setDesecToken($token);
            $this->configurationManager->desecEmail = $email;
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

            // Set the domain; skip the reachability validation because the domain was just
            // created and DNS propagation may not have completed yet.
            $this->configurationManager->setDomain($domain, true);

            // Perform the first DNS IP update so the record is populated immediately
            $this->updateIpIfDesecDomain();

            return $response->withStatus(201)->withHeader('Location', '.');
        } catch (InvalidSettingConfigurationException $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        } catch (\Exception $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
    }

    /**
     * Updates the deSEC DNS A/AAAA record with the current public IP of this host.
     * Uses deSEC's DynDNS2-compatible update endpoint, which auto-detects the requester's IP.
     * Safe to call frequently; the endpoint returns "nochg" when the IP has not changed.
     * Errors are logged but never thrown, so callers are not interrupted.
     */
    public function updateIpIfDesecDomain(): void {
        if (!$this->configurationManager->isDesecDomain()) {
            return;
        }

        $domain = $this->configurationManager->domain;
        $token  = $this->configurationManager->getDesecToken();

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
     * Creates a new deSEC account and returns the API token from the response.
     *
     * @throws \Exception on network failure or an unexpected API response
     */
    private function registerDesecAccount(string $email, string $password): string {
        try {
            $res = $this->guzzleClient->post(self::DESEC_API_BASE . '/auth/', [
                'json' => ['email' => $email, 'password' => $password],
            ]);
        } catch (TransferException $e) {
            throw new \Exception('Could not reach the deSEC API: ' . $e->getMessage());
        }

        $httpCode = $res->getStatusCode();
        $body = $res->getBody()->getContents();

        if ($httpCode === 400) {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($data) && isset($data['email'])) {
                throw new \Exception(
                    'This email address is already registered at deSEC. '
                    . 'Please log in at https://desec.io to retrieve your token and set up your domain manually.',
                );
            }
            throw new \Exception('Registration at deSEC failed (HTTP 400): ' . $body);
        }

        if ($httpCode !== 201) {
            throw new \Exception(
                'Unexpected response from deSEC during account registration '
                . '(HTTP ' . $httpCode . '): ' . $body,
            );
        }

        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['token']['token']) || !is_string($data['token']['token'])) {
            throw new \Exception(
                'Could not extract the API token from the deSEC response. Please try again.',
            );
        }

        return $data['token']['token'];
    }

    /**
     * Registers a new dedyn.io subdomain and returns its full name.
     * Retries with a different random slug on name conflicts.
     *
     * @throws \Exception when all attempts fail or a network/API error occurs
     */
    private function registerDesecDomain(string $token): string {
        $lastError = '';

        for ($attempt = 0; $attempt < self::MAX_SLUG_ATTEMPTS; $attempt++) {
            $slug = bin2hex(random_bytes(self::SLUG_BYTES));
            $domain = $slug . self::DEDYN_SUFFIX;

            try {
                $res = $this->guzzleClient->post(self::DESEC_API_BASE . '/domains/', [
                    'headers' => ['Authorization' => 'Token ' . $token],
                    'json' => ['name' => $domain],
                ]);
            } catch (TransferException $e) {
                throw new \Exception('Could not reach the deSEC API: ' . $e->getMessage());
            }

            $httpCode = $res->getStatusCode();

            if ($httpCode === 201) {
                return $domain;
            }

            if ($httpCode === 409) {
                // Slug already taken — try another one
                $lastError = '"' . $domain . '" is already taken';
                continue;
            }

            $body = $res->getBody()->getContents();
            throw new \Exception(
                'Unexpected response from deSEC during domain registration '
                . '(HTTP ' . $httpCode . '): ' . $body,
            );
        }

        throw new \Exception(
            'Could not register a free dedyn.io domain after ' . self::MAX_SLUG_ATTEMPTS . ' attempts'
            . ($lastError !== '' ? ' (' . $lastError . ')' : '') . '. Please try again.',
        );
    }
}
