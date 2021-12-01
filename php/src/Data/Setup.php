<?php

namespace AIO\Data;

use AIO\Auth\PasswordGenerator;
use Psr\Http\Message\ServerRequestInterface as Request;

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

    public function CanBeInstalled(?Request $request) : bool {
        if (file_exists(DataConst::GetConfigFile())) {
            return false;
        }

        if ($request === null) {
            return true;
        }

        $uri = $request->getUri();
        if ($uri->getPort() === '8080') {
            if (!file_exists(DataConst::GetTempSetupFile())) {
                if(!is_dir(DataConst::GetDataDirectory())) {
                    mkdir(DataConst::GetDataDirectory());
                }
                file_put_contents(DataConst::GetTempSetupFile(), '');
                return false;
            } else {
                    unlink(DataConst::GetTempSetupFile());
                    return true;
            }
        }

        return true;
    }
}
