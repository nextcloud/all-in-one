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
    private const string DEDYN_SUFFIX = '.dedyn.io';
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
        if ($this->configurationManager->domain !== '') {
            $response->getBody()->write('A domain is already configured. Reset the AIO instance first to register a new domain.');
            return $response->withStatus(422);
        }

        $accountAlreadyRegistered = $this->configurationManager->isDesecAccountRegistered();

        if ($accountAlreadyRegistered) {
            $token = $this->configurationManager->desecToken;
        } else {
            $email = trim((string)($request->getParsedBody()['desec_email'] ?? ''));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $response->getBody()->write('Please provide a valid email address.');
                return $response->withStatus(422);
            }
        }

        $slug = trim((string)($request->getParsedBody()['desec_slug'] ?? ''));
        if ($slug !== '' && !preg_match(self::SLUG_PATTERN, $slug)) {
            $response->getBody()->write(
                'The desired subdomain must contain only lowercase letters, digits and hyphens, '
                . 'be between 1 and 63 characters long, and must not start or end with a hyphen.'
            );
            return $response->withStatus(422);
        }

        try {
            if (!$accountAlreadyRegistered) {
                // 24 random bytes → 48-char hex password, stored so the user can log in at desec.io.
                $password = bin2hex(random_bytes(24));
                $token = $this->registerDesecAccount($email, $password);

                $this->configurationManager->startTransaction();
                $this->configurationManager->desecToken    = $token;
                $this->configurationManager->desecPassword = $password;
                $this->configurationManager->desecEmail    = $email;
                $this->configurationManager->commitTransaction();
            }

            $domain = $this->registerDesecDomain($token, $slug);

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

            $this->configurationManager->setDomain($domain, true);
            $this->updateIpIfDesecDomain();

            return $response->withStatus(201)->withHeader('Location', '.');
        } catch (\Exception $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
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

    private function registerDesecDomain(string $token, string $slug): string {
        $random   = $slug === '';
        $attempts = $random ? self::MAX_SLUG_ATTEMPTS : 1;

        for ($i = 0; $i < $attempts; $i++) {
            $domain = ($random ? bin2hex(random_bytes(self::SLUG_BYTES)) : $slug) . self::DEDYN_SUFFIX;

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
