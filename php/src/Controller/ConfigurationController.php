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

    public function SetConfig(Request $request, Response $response, array $args) : Response {
        try {
            $body = $request->getParsedBody();
            if (is_array($body)) {
                if (is_string($body['domain']))
                    $this->configurationManager->SetDomain($body['domain']);

                $currentMasterPassword = is_string($body['current-master-password']) ? $body['current-master-password'] : null;
                $newMasterPassword = is_string($body['new-master-password']) ? $body['new-master-password'] : null;
                if ($currentMasterPassword !== null || $newMasterPassword !== null)
                    $this->configurationManager->ChangeMasterPassword($currentMasterPassword ?? '', $newMasterPassword ?? '');

                if (is_string($body['borg_backup_host_location']))
                    $this->configurationManager->SetBorgBackupHostLocation($body['borg_backup_host_location']);

                $borgRestoreHostLocation = is_string($body['borg_restore_host_location']) ? $body['borg_restore_host_location'] : null;
                $borgRestorePassword = is_string($body['borg_restore_password']) ? $body['borg_restore_password'] : null;
                if ($borgRestoreHostLocation !== null || $borgRestorePassword !== null)
                    $this->configurationManager->SetBorgRestoreHostLocationAndPassword($borgRestoreHostLocation ?? '', $borgRestorePassword ?? '');

                if (is_string($body['daily_backup_time']))
                    $this->configurationManager->SetDailyBackupTime(
                        $body['daily_backup_time'],
                        isset($body['automatic_updates']),
                        isset($body['success_notification']));

                if (isset($body['delete_daily_backup_time']))
                    $this->configurationManager->DeleteDailyBackupTime();

                if (is_string($body['additional_backup_directories']))
                    $this->configurationManager->SetAdditionalBackupDirectories($body['additional_backup_directories']);

                if (isset($body['delete_timezone']))
                    $this->configurationManager->DeleteTimezone();

                if (is_string($body['timezone']))
                    $this->configurationManager->SetTimezone($body['timezone']);

                if (isset($body['options-form'])) {
                    if (isset($body['collabora']) && isset($body['onlyoffice']))
                        throw new InvalidSettingConfigurationException("Collabora and Onlyoffice are not allowed to be enabled at the same time!");
                    $this->configurationManager->SetClamavEnabledState(isset($body['clamav']) ? 1 : 0);
                    $this->configurationManager->SetOnlyofficeEnabledState(isset($body['onlyoffice']) ? 1 : 0);
                    $this->configurationManager->SetCollaboraEnabledState(isset($body['collabora']) ? 1 : 0);
                    $this->configurationManager->SetTalkEnabledState(isset($body['talk']) ? 1 : 0);
                    $this->configurationManager->SetTalkRecordingEnabledState(isset($body['talk-recording']) ? 1 : 0);
                    $this->configurationManager->SetImaginaryEnabledState(isset($body['imaginary']) ? 1 : 0);
                    $this->configurationManager->SetFulltextsearchEnabledState(isset($body['fulltextsearch']) ? 1 : 0);
                    $this->configurationManager->SetDockerSocketProxyEnabledState(isset($body['docker-socket-proxy']) ? 1 : 0);
                    $this->configurationManager->SetWhiteboardEnabledState(isset($body['whiteboard']) ? 1 : 0);
                }

                if (isset($body['delete_collabora_dictionaries']))
                    $this->configurationManager->DeleteCollaboraDictionaries();

                if (is_string($body['collabora_dictionaries']))
                    $this->configurationManager->SetCollaboraDictionaries($body['collabora_dictionaries']);

                if (isset($body['delete_borg_backup_host_location']))
                    $this->configurationManager->DeleteBorgBackupHostLocation();
            }

            return $response->withStatus(201)->withHeader('Location', '/');
        } catch (InvalidSettingConfigurationException $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
    }
}
