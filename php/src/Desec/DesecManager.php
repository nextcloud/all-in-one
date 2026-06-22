<?php
declare(strict_types=1);

namespace AIO\Desec;

use AIO\Data\ConfigurationManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

class DesecManager {
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
     * @return bool true when the domain was fully registered; false when a new account was
     *         just created and we are now awaiting the user's email verification (a normal,
     *         non-error outcome — the awaiting-verification UI explains the next step).
     * @throws \Exception on any validation or API error
     */
    public function register(string $email, string $slug, string $password = ''): bool {
        if ($this->configurationManager->domain !== '') {
            throw new \Exception('A domain is already configured. Reset the AIO instance first to register a new domain.');
        }

        $validatedSlug = $this->validateSlug($slug);

        // Persist the requested slug so the form can pre-fill it when it re-renders on the
        // next step of the flow (e.g. after email verification). Cleared once a domain is set.
        $this->configurationManager->desecSlug = $validatedSlug;

        [$token, $isNewAccount] = $this->obtainToken($email, $password);

        // An empty token means a brand-new account was created but its email is not yet
        // verified. That is not an error: the account state is already persisted, so report
        // "awaiting verification" to the caller and let it re-render the awaiting UI.
        if ($token === '') {
            return false;
        }

        $domain = $this->registerDomain($token, $validatedSlug);

        if ($isNewAccount) {
            $this->createWildcardCname($token, $domain);
        }

        $this->configurationManager->aioCommunityContainers = ["caddy", "dnsmasq"];
        $this->configurationManager->setDomain($domain, true);
        // Registration is complete; the stored slug is no longer needed.
        $this->configurationManager->desecSlug = '';
        $this->updateIpIfDesecDomain();

        return true;
    }

    /**
     * Resolves the deSEC API token to use for domain registration, creating or
     * logging into an account as needed.
     *
     * deSEC's POST /auth/ endpoint does NOT return a token: it replies 202 and
     * emails a verification link. A token can only be obtained from /auth/login/
     * once the email has been verified. This method therefore drives a small
     * state machine across multiple form submissions:
     *
     *  - A token is already stored (a previous attempt got past account setup but
     *    failed at domain registration) → reuse it.
     *  - The user supplied a password → log in to their existing, verified account.
     *  - We are awaiting verification of an account we created earlier → try to log
     *    in with the stored credentials; succeed once the email is verified,
     *    otherwise ask the user to verify and try again.
     *  - Otherwise → create a new account and ask the user to verify their email.
     *
     * @return array{0: string, 1: bool} the token and whether it belongs to a
     *         freshly created (AIO-managed) account, which controls wildcard setup.
     * @throws \Exception with a user-facing message when verification is pending or on any API error
     */
    private function obtainToken(string $email, string $password): array {
        if ($this->configurationManager->isDesecAccountRegistered()) {
            return [$this->configurationManager->desecToken, false];
        }

        $validatedPassword = trim($password);

        // An account we created earlier is awaiting email verification.
        if ($this->configurationManager->isDesecAwaitingVerification()) {
            $storedEmail    = $this->configurationManager->desecEmail;
            // Prefer a freshly entered password (e.g. the user changed it), else the generated one.
            $userSuppliedPassword = $validatedPassword !== '';
            $loginPassword  = $userSuppliedPassword ? $validatedPassword : $this->configurationManager->desecPassword;
            $token          = $this->loginAfterVerification($storedEmail, $loginPassword);
            // Storing the token flips the state from "awaiting verification" to "account
            // registered" (see ConfigurationManager::isDesecAwaitingVerification()).
            $this->configurationManager->desecToken = $token;
            // If the user logged in with their own password (the email already had a deSEC
            // account, so no AIO account was created), the previously generated password is
            // wrong and must not be persisted or later revealed. Clear it so a stored,
            // non-empty password reliably means "AIO generated this account".
            if ($userSuppliedPassword) {
                $this->configurationManager->desecPassword = '';
            }
            return [$token, !$userSuppliedPassword];
        }

        $validatedEmail = $this->validateEmail($email);

        if ($validatedPassword !== '') {
            // The user supplied their existing deSEC password — log in instead of registering.
            // Store an empty password: the token is all we need; the user's password must not be persisted.
            $token = $this->loginAccount($validatedEmail, $validatedPassword);
            $this->saveAccountCredentials($token, '', $validatedEmail);
            return [$token, false];
        }

        // Create a new account. 24 random bytes → 48-char hex password; satisfies deSEC's
        // minimum length and lets the user log in at desec.io if they ever need to.
        $generatedPassword = bin2hex(random_bytes(24));
        $this->registerAccount($validatedEmail, $generatedPassword);
        // Persisting email + password (but no token, no domain) is exactly the
        // "awaiting verification" state, see ConfigurationManager::isDesecAwaitingVerification().
        $this->configurationManager->startTransaction();
        $this->configurationManager->desecPassword = $generatedPassword;
        $this->configurationManager->desecEmail    = $validatedEmail;
        $this->configurationManager->commitTransaction();

        // This is not an error: the account was requested successfully and we now wait for
        // the user to verify their email. Signal it with an empty token so register() can
        // stop cleanly and the caller can re-render the awaiting-verification UI (which
        // already explains the next step) instead of surfacing an error toast.
        return ['', true];
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
     * Requests creation of a new deSEC account.
     *
     * deSEC replies 202 Accepted and emails a verification link; no API token is
     * returned here and the account is unusable until the email is verified. For
     * privacy reasons deSEC also returns 202 when the email is already registered
     * (without sending a mail), so a 202 cannot be treated as proof of a new account.
     * The captcha field is omitted; deSEC requires it only at email-verification time,
     * which the user completes in the browser via the emailed link.
     *
     * @throws \Exception on network failure or an unexpected HTTP response
     */
    public function registerAccount(string $email, string $password): void {
        try {
            $res = $this->guzzleClient->post($this->configurationManager->desecApiBase . '/auth/', [
                'json' => ['email' => $email, 'password' => $password],
            ]);
        } catch (TransferException $e) {
            throw new \Exception('Could not reach the deSEC API: ' . $e->getMessage());
        }

        $code = $res->getStatusCode();

        if ($code !== 202) {
            throw new \Exception('Unexpected response from deSEC during account registration (HTTP ' . $code . '): ' . $res->getBody()->getContents());
        }
    }

    /**
     * Attempts to log in after the user was asked to verify a freshly created account.
     *
     * A login failure here has two common causes that we cannot tell apart, because
     * deSEC returns 202 both for a genuinely new account and for one whose email was
     * already registered (to prevent email enumeration):
     *   1. The account is new but its email has not been verified yet.
     *   2. The email already belonged to an existing deSEC account, so no new account
     *      (and no verification mail) was created and our generated password is wrong.
     * The message covers both and points to the fix for each.
     *
     * @throws \Exception with a friendly hint when login is not yet possible
     */
    private function loginAfterVerification(string $email, string $password): string {
        try {
            return $this->loginAccount($email, $password);
        } catch (\Exception $e) {
            throw new \Exception(
                'Could not log in to deSEC for ' . $email . ' yet. Two things to check:' . "\n"
                . '• If deSEC emailed you a verification link, please click it and then try again.' . "\n"
                . '• If this email already had a deSEC account, no new account was created. '
                . 'In that case, enter your existing deSEC password in the password field below and try again.'
            );
        }
    }

    /**
     * Authenticates with an existing deSEC account and returns the API token issued for it.
     *
     * @throws \Exception on invalid credentials, network failure, or an unexpected HTTP response
     */
    public function loginAccount(string $email, string $password): string {
        try {
            $res = $this->guzzleClient->post($this->configurationManager->desecApiBase . '/auth/login/', [
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
     * When a specific slug is requested and creation fails because the name is unavailable
     * (HTTP 400/409) or the account's domain limit is reached (HTTP 403), the domain may
     * already belong to this very account — a user reusing a slug they registered earlier.
     * In that case we reuse the existing domain instead of failing, so an existing-account
     * login can point AIO at a domain the user already owns. (deSEC returns 400 when a name
     * conflicts with another user's zone and 403 once the per-account domain limit is hit;
     * both look like a failure here even though the user owns the name.)
     *
     * @return string the fully-qualified domain name that was registered
     * @throws \Exception if the slug is taken by someone else, on network failure, or after exhausting random attempts
     */
    public function registerDomain(string $token, string $slug): string {
        $random   = $slug === '';
        $attempts = $random ? self::MAX_SLUG_ATTEMPTS : 1;

        for ($i = 0; $i < $attempts; $i++) {
            $domain = ($random ? bin2hex(random_bytes(self::SLUG_BYTES)) : $slug) . ConfigurationManager::DEDYN_SUFFIX;

            try {
                $res = $this->guzzleClient->post($this->configurationManager->desecApiBase . '/domains/', [
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

            // For a user-specified slug, the name may be unavailable (400/409) or the account's
            // domain limit may be reached (403) precisely because the user already owns this
            // domain. Reuse it rather than failing.
            if (!$random && ($code === 400 || $code === 403 || $code === 409)) {
                if ($this->ownsDomain($token, $domain)) {
                    return $domain;
                }
                if ($code === 403) {
                    throw new \Exception(
                        'Your deSEC account has reached its domain limit and "' . $domain . '" is not '
                        . 'one of your existing domains. Remove an unused domain at desec.io, or contact '
                        . 'deSEC support to raise the limit, then try again.'
                    );
                }
                throw new \Exception('"' . $domain . '" is already taken. Please choose a different subdomain and try again.');
            }

            if ($code === 409) {
                // Random slug collided with an existing name — try another.
                continue;
            }

            throw new \Exception('Unexpected response from deSEC during domain registration (HTTP ' . $code . '): ' . $res->getBody()->getContents());
        }

        throw new \Exception('Could not register a free dedyn.io domain after ' . self::MAX_SLUG_ATTEMPTS . ' attempts. Please try again.');
    }

    /**
     * Checks whether the authenticated account already owns the given domain.
     *
     * Used to recover from a failed creation when the user is reusing a slug they
     * registered earlier: GET /domains/{name}/ returns 200 only for a domain the
     * token's account owns, 404 otherwise.
     *
     * @throws \Exception on network failure or an unexpected HTTP response
     */
    private function ownsDomain(string $token, string $domain): bool {
        try {
            $res = $this->guzzleClient->get($this->configurationManager->desecApiBase . '/domains/' . $domain . '/', [
                'headers' => ['Authorization' => 'Token ' . $token],
            ]);
        } catch (TransferException $e) {
            throw new \Exception('Could not reach the deSEC API: ' . $e->getMessage());
        }

        $code = $res->getStatusCode();

        if ($code === 200) {
            return true;
        }

        if ($code === 404) {
            return false;
        }

        throw new \Exception('Unexpected response from deSEC while checking domain ownership (HTTP ' . $code . '): ' . $res->getBody()->getContents());
    }

    /**
     * Creates a wildcard CNAME rrset (*.domain → domain.) for a newly registered domain.
     * Errors are logged but do not abort the overall registration.
     */
    private function createWildcardCname(string $token, string $domain): void {
        try {
            $res = $this->guzzleClient->post($this->configurationManager->desecApiBase . '/domains/' . $domain . '/rrsets/', [
                'headers' => ['Authorization' => 'Token ' . $token],
                'json'    => [
                    'subname' => '*',
                    'type'    => 'CNAME',
                    'ttl'     => 3600,
                    'records' => [$domain . '.'],
                ],
            ]);
        } catch (TransferException $e) {
            error_log('Could not create wildcard CNAME for ' . $domain . ': ' . $e->getMessage());
            return;
        }

        $code = $res->getStatusCode();
        if ($code !== 201) {
            error_log('Unexpected response when creating wildcard CNAME for ' . $domain . ' (HTTP ' . $code . '): ' . $res->getBody()->getContents());
        }
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
            $res    = $this->guzzleClient->get($this->configurationManager->desecUpdateUrl, [
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
