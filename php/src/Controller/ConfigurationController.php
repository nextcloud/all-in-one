<?php

namespace AIO\Controller;

use AIO\ContainerDefinitionFetcher;
use AIO\Data\ConfigurationManager;
use AIO\Data\InvalidSettingConfigurationException;
use AIO\Docker\DockerActionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ConfigurationController
{
    private ConfigurationManager $configurationManager;

    public function __construct(
        ConfigurationManager $configurationManager
    ) {
        $this->configurationManager = $configurationManager;
    }

    public function SetConfig(Request $request, Response $response, $args) : Response {
        try {
            if (isset($request->getParsedBody()['domain'])) {
                $domain = $request->getParsedBody()['domain'] ?? '';
                $this->configurationManager->SetDomain($domain);
            }

            if (isset($request->getParsedBody()['current-master-password']) || isset($request->getParsedBody()['new-master-password'])) {
                $currentMasterPassword = $request->getParsedBody()['current-master-password'] ?? '';
                $newMasterPassword = $request->getParsedBody()['new-master-password'] ?? '';
                $this->configurationManager->ChangeMasterPassword($currentMasterPassword, $newMasterPassword);
            }

            if (isset($request->getParsedBody()['borg_backup_host_location'])) {
                $location = $request->getParsedBody()['borg_backup_host_location'] ?? '';
                $this->configurationManager->SetBorgBackupHostLocation($location);
            }

            if (isset($request->getParsedBody()['borg_restore_host_location']) || isset($request->getParsedBody()['borg_restore_password'])) {
                $restoreLocation = $request->getParsedBody()['borg_restore_host_location'] ?? '';
                $borgPassword = $request->getParsedBody()['borg_restore_password'] ?? '';
                $this->configurationManager->SetBorgRestoreHostLocationAndPassword($restoreLocation, $borgPassword);
            }

            if (isset($request->getParsedBody()['daily_backup_time'])) {
                $dailyBackupTime = $request->getParsedBody()['daily_backup_time'] ?? '';
                $this->configurationManager->SetDailyBackupTime($dailyBackupTime);
            }

            if (isset($request->getParsedBody()['delete_daily_backup_time'])) {
                $this->configurationManager->DeleteDailyBackupTime();
            }

            if (isset($request->getParsedBody()['delete_timezone'])) {
                $this->configurationManager->DeleteTimezone();
            }

            if (isset($request->getParsedBody()['timezone'])) {
                $timezone = $request->getParsedBody()['timezone'] ?? '';
                $this->configurationManager->SetTimezone($timezone);
            }

            if (isset($request->getParsedBody()['options-form'])) {
                if (isset($request->getParsedBody()['collabora']) && isset($request->getParsedBody()['onlyoffice'])) {
                    throw new InvalidSettingConfigurationException("Collabora and Onlyoffice are not allowed to be enabled at the same time!");
                }
                if (isset($request->getParsedBody()['clamav'])) {
                    $this->configurationManager->SetClamavEnabledState(1);
                } else {
                    $this->configurationManager->SetClamavEnabledState(0);
                }
                if (isset($request->getParsedBody()['onlyoffice'])) {
                    $this->configurationManager->SetOnlyofficeEnabledState(1);
                } else {
                    $this->configurationManager->SetOnlyofficeEnabledState(0);
                }
                if (isset($request->getParsedBody()['collabora'])) {
                    $this->configurationManager->SetCollaboraEnabledState(1);
                } else {
                    $this->configurationManager->SetCollaboraEnabledState(0);
                }
                if (isset($request->getParsedBody()['talk'])) {
                    $this->configurationManager->SetTalkEnabledState(1);
                } else {
                    $this->configurationManager->SetTalkEnabledState(0);
                }
            }

            return $response->withStatus(201)->withHeader('Location', '/');
        } catch (InvalidSettingConfigurationException $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
    }
}
