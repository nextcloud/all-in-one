<?php
declare(strict_types=1);

namespace AIO\Data;

use AIO\Auth\PasswordGenerator;

readonly class Setup {
    public function __construct(
        private PasswordGenerator $passwordGenerator,
        private ConfigurationManager $configurationManager,
    ) {
    }

    public function Setup() : string {
        if(!$this->CanBeInstalled()) {
            return '';
        }

        $password = $this->passwordGenerator->GeneratePassword(8);
        // Save the password and the default container selection to the config, so it gets persisted even if
        // people don't change anything in the UI. Without this any change to the defaults would be applied to
        // existing instances.
        $this->configurationManager->startTransaction();
        $this->configurationManager->password = $password;
        // Get the defaults for these config options from their own getters to avoid duplication of defaults.
        // Makes the code look funny but works.
        $this->configurationManager->officeSuite = $this->configurationManager->officeSuite;
        $this->configurationManager->isClamavEnabled = $this->configurationManager->isClamavEnabled;
        $this->configurationManager->isFulltextsearchEnabled = $this->configurationManager->isFulltextsearchEnabled;
        $this->configurationManager->isImaginaryEnabled = $this->configurationManager->isImaginaryEnabled;
        $this->configurationManager->isTalkEnabled = $this->configurationManager->isTalkEnabled;
        $this->configurationManager->isTalkRecordingEnabled = $this->configurationManager->isTalkRecordingEnabled;
        $this->configurationManager->isDockerSocketProxyEnabled = $this->configurationManager->isDockerSocketProxyEnabled;
        $this->configurationManager->isHarpEnabled = $this->configurationManager->isHarpEnabled;
        $this->configurationManager->isWhiteboardEnabled = $this->configurationManager->isWhiteboardEnabled;
        $this->configurationManager->commitTransaction();
        return $password;
    }

    public function CanBeInstalled() : bool {
        return !file_exists(DataConst::GetConfigFile());
    }
}
