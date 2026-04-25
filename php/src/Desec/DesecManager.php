<?php
declare(strict_types=1);

namespace AIO\Desec;

use AIO\Data\ConfigurationManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

class DesecManager {
    private const string DESEC_API_BASE = 'https://desec.io/api/v1';
    private const int MAX_SLUG_ATTEMPTS = 5;
    private const int SLUG_BYTES = 5; // bin2hex → 10-char slug
    private const string SLUG_PATTERN = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

    private Client $guzzleClient;

    public function __construct(
        private readonly ConfigurationManager $configurationManager,
    ) {
        $this->guzzleClient = new Client([
            'timeout' => 15,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    /**
     * Full registration flow: validates inputs, creates an account if needed,
     * registers the domain, enables required containers, and updates the DNS record.
     *
     * When $password is non-empty the user is logging into an existing deSEC account
     * rather than creating a new one. When $password is empty a new account is created
     * with a randomly generated password (unless an account was already registered in a
     * previous attempt).
     *
     * @throws \Exception on any validation or API error
     */
    public function register(string $email, string $slug, string $password = ''): void {
        if ($this->configurationManager->domain !== '') {
            throw new \Exception('A domain is already configured. Reset the AIO instance first to register a new domain.');
        }

        $accountAlreadyRegistered = $this->configurationManager->isDesecAccountRegistered();
        $token = $accountAlreadyRegistered
            ? $this->configurationManager->desecToken
            : null;

        $validatedSlug = $this->validateSlug($slug);

        if (!$accountAlreadyRegistered) {
            $validatedEmail    = $this->validateEmail($email);
            $validatedPassword = trim($password);

            if ($validatedPassword !== '') {
                // The user supplied their existing deSEC password — log in instead of registering.
                // Store an empty password: the token is all we need; the user's password must not be persisted.
                $token = $this->loginAccount($validatedEmail, $validatedPassword);
                $this->saveAccountCredentials($token, '', $validatedEmail);
            } else {
                // 24 random bytes → 48-char hex password; satisfies deSEC's minimum length
                // and lets the user log in at desec.io if they ever need to.
                $generatedPassword = bin2hex(random_bytes(24));
                $token             = $this->registerAccount($validatedEmail, $generatedPassword);
                $this->saveAccountCredentials($token, $generatedPassword, $validatedEmail);
            }
        }

        $domain = $this->registerDomain($token, $validatedSlug);
        $this->enableDesecContainers();
        $this->configurationManager->setDomain($domain, true);
        $this->updateIpIfDesecDomain();
    }

    /**
     * Validates an email address string.
     *
     * @throws \Exception if the email is empty or syntactically invalid
     */
    private function validateEmail(string $email): string {
        $email = trim($email);
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \Exception('Please provide a valid email address.');
        }
        return $email;
    }

    /**
     * Validates an optional subdomain slug.
     * Returns an empty string when the caller wants a randomly generated slug.
     *
     * @throws \Exception if the slug is non-empty but does not match the allowed pattern
     */
    private function validateSlug(string $slug): string {
        $slug = trim($slug);
        if ($slug !== '' && !preg_match(self::SLUG_PATTERN, $slug)) {
            throw new \Exception(
                'The desired subdomain must contain only lowercase letters, digits and hyphens, '
                . 'be between 1 and 63 characters long, and must not start or end with a hyphen.'
            );
        }
        return $slug;
    }

    /**
     * Creates a new deSEC account and returns the API token issued for it.
     *
     * @throws \Exception on network failure or an unexpected HTTP response
     */
    public function registerAccount(string $email, string $password): string {
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
                throw new AlreadyRegisteredException($email);
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
     * Authenticates with an existing deSEC account and returns the API token issued for it.
     *
     * @throws \Exception on invalid credentials, network failure, or an unexpected HTTP response
     */
    public function loginAccount(string $email, string $password): string {
        try {
            $res = $this->guzzleClient->post(self::DESEC_API_BASE . '/auth/login/', [
                'json' => ['email' => $email, 'password' => $password],
            ]);
        } catch (TransferException $e) {
            throw new \Exception('Could not reach the deSEC API: ' . $e->getMessage());
        }

        $code = $res->getStatusCode();
        $body = $res->getBody()->getContents();

        if ($code === 400 || $code === 403) {
            throw new \Exception('Could not log in to deSEC: invalid email address or password.');
        }

        if ($code !== 200 && $code !== 201) {
            throw new \Exception('Unexpected response from deSEC during login (HTTP ' . $code . '): ' . $body);
        }

        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['token']) || !is_string($data['token'])) {
            throw new \Exception('Could not extract the API token from the deSEC login response. Please try again.');
        }

        return $data['token'];
    }

    /**
     * Registers a dedyn.io domain for the authenticated account.
     * When $slug is empty a random 10-character slug is tried up to MAX_SLUG_ATTEMPTS times.
     *
     * @return string the fully-qualified domain name that was registered
     * @throws \Exception if the slug is taken, on network failure, or after exhausting random attempts
     */
    public function registerDomain(string $token, string $slug): string {
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

    /**
     * Persists deSEC account credentials to the AIO configuration atomically.
     */
    public function saveAccountCredentials(string $token, string $password, string $email): void {
        $this->configurationManager->startTransaction();
        $this->configurationManager->desecToken    = $token;
        $this->configurationManager->desecPassword = $password;
        $this->configurationManager->desecEmail    = $email;
        $this->configurationManager->commitTransaction();
    }

    /**
     * Ensures the caddy and dnsmasq community containers are enabled.
     */
    public function enableDesecContainers(): void {
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

    /**
     * Updates the deSEC dynamic-DNS record with the current public IP.
     * Does nothing when the configured domain is not a deSEC-managed dedyn.io domain.
     */
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
}
