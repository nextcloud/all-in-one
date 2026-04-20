<?php
declare(strict_types=1);

namespace AIO\Auth;

use AIO\Data\ConfigurationManager;
use AIO\Data\DataConst;
use \DateTime;

readonly class AuthManager {
    public const string SESSION_KEY = 'aio_authenticated';

    public function __construct(
        private ConfigurationManager $configurationManager
    ) {
    }

    public function CheckCredentials(string $password) : bool {
        return hash_equals($this->configurationManager->password, $password);
    }

    public function CheckToken(string $token) : bool {
        return hash_equals($this->configurationManager->aioToken, $token);
    }

    public function SetAuthState(bool $isLoggedIn) : void {

        if (!$this->IsAuthenticated() && $isLoggedIn === true) {
            session_regenerate_id(true);
            $date = new DateTime();
            $dateTime = $date->getTimestamp();
            $_SESSION['date_time'] = $dateTime;

            $df = disk_free_space(DataConst::GetSessionDirectory());
            if ($df !== false && (int)$df < 10240) {
                error_log(DataConst::GetSessionDirectory() . " has only less than 10KB free space. The login might not succeed because of that!");
            }

            file_put_contents(DataConst::GetSessionDateFile(), (string)$dateTime);
        }

        $_SESSION[self::SESSION_KEY] = $isLoggedIn;
    }

    /**
     * Migrates the authenticated state from an old session (different cookie name) to the new session.
     * Unlike SetAuthState, this method preserves the original login timestamp and does not update
     * the session_date_file, so the session deduplicator is not triggered. This keeps the old session
     * file alive in case the response carrying the new cookie is lost (e.g., due to a 502 error during
     * a mastercontainer update), allowing the client to retry with the old cookie.
     */
    public function MigrateAuthState(int $oldTimestamp) : void {
        $_SESSION[self::SESSION_KEY] = true;
        $_SESSION['date_time'] = $oldTimestamp;
    }

    public function IsAuthenticated() : bool {
        return isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] === true;
    }
}
