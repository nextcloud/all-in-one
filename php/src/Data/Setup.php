<?php

namespace AIO\Data;

use AIO\Auth\PasswordGenerator;

class Setup
{
    private PasswordGenerator $passwordGenerator;
    private ConfigurationManager $configurationManager;

    public function __construct(
        PasswordGenerator $passwordGenerator,
        ConfigurationManager $configurationManager) {
        $this->passwordGenerator = $passwordGenerator;
        $this->configurationManager = $configurationManager;
    }

    public function Setup() : string {
        if(!$this->CanBeInstalled()) {
            return '';
        }

        $password = $this->passwordGenerator->GeneratePassword(8);
        $this->configurationManager->SetPassword($password);
        return $password;
    }

    public function CanBeInstalled() : bool {
        return !file_exists(DataConst::GetConfigFile());
    }
}
