<?php

namespace AIO\Auth;

use AIO\Data\ConfigurationManager;

class AuthManager {
    private const SESSION_KEY = 'aio_authenticated';
    private ConfigurationManager $configurationManager;

    public function __construct(ConfigurationManager $configurationManager) {
        $this->configurationManager = $configurationManager;
    }

    public function CheckCredentials(string $password) : bool {
        return hash_equals($this->configurationManager->GetPassword(), $password);
    }

    public function CheckToken(string $token) : bool {
        return hash_equals($this->configurationManager->GetToken(), $token);
    }

    public function SetAuthState(bool $isLoggedIn) : void {
        $_SESSION[self::SESSION_KEY] = $isLoggedIn;
    }

    public function IsAuthenticated() : bool {
        return isset($_SESSION[self::SESSION_KEY]) && $_SESSION[self::SESSION_KEY] === true;
    }
}
