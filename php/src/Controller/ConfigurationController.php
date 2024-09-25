<?php

namespace AIO\Controller;

use AIO\Data\ConfigurationManager;
use AIO\Data\InvalidSettingConfigurationException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

readonly class ConfigurationController {
    public function SetConfig(Request $request, Response $response, array $args): Response {
        try {
            $body = $request->getParsedBody();
            if (is_array($body)) {
                $config = ConfigurationManager::loadConfigFile();

                if (is_string($body['domain']))
                    $config->setDomain($body['domain']);

                $currentMasterPassword = is_string($body['current-master-password']) ? $body['current-master-password'] : null;
                $newMasterPassword = is_string($body['new-master-password']) ? $body['new-master-password'] : null;
                if ($currentMasterPassword !== null || $newMasterPassword !== null)
                    $config->changeMasterPassword($currentMasterPassword ?? '', $newMasterPassword ?? '');

                if (is_string($body['borg_backup_host_location']))
                    $config->setBorgLocation($body['borg_backup_host_location']);

                $borgRestoreHostLocation = is_string($body['borg_restore_host_location']) ? $body['borg_restore_host_location'] : null;
                $borgRestorePassword = is_string($body['borg_restore_password']) ? $body['borg_restore_password'] : null;
                if ($borgRestoreHostLocation !== null || $borgRestorePassword !== null)
                    $config->setBorgRestoreLocationAndPassword($borgRestoreHostLocation ?? '', $borgRestorePassword ?? '');

                if (is_string($body['daily_backup_time']))
                    ConfigurationManager::SetDailyBackupTime(
                        $body['daily_backup_time'],
                        isset($body['automatic_updates']),
                        isset($body['success_notification']));

                if (isset($body['delete_daily_backup_time']))
                    $config->deleteTimezone();

                if (is_string($body['additional_backup_directories']))
                    ConfigurationManager::SetAdditionalBackupDirectories($body['additional_backup_directories']);

                if (isset($body['delete_timezone']))
                    $config->DeleteTimezone();

                if (is_string($body['timezone']))
                    $config->SetTimezone($body['timezone']);

                if (isset($body['options-form'])) {
                    if (isset($body['collabora']) && isset($body['onlyoffice']))
                        throw new InvalidSettingConfigurationException("Collabora and Onlyoffice are not allowed to be enabled at the same time!");
                    $config->enableClamav(isset($body['clamav']));
                    $config->enableOnlyoffice(isset($body['onlyoffice']));
                    $config->enableCollabora(isset($body['collabora']));
                    $config->talkEnabled = isset($body['talk']);
                    $config->enableTalkRecording(isset($body['talk-recording']));
                    $config->imaginaryEnabled = isset($body['imaginary']);
                    $config->fulltextsearchEnabled = isset($body['fulltextsearch']);
                    $config->dockerSocketProxyEnabled = isset($body['docker-socket-proxy']);
                    $config->whiteboardEnabled = isset($body['whiteboard']);
                }

                if (isset($body['delete_collabora_dictionaries']))
                    $config->DeleteCollaboraDictionaries();

                if (is_string($body['collabora_dictionaries']))
                    $config->SetCollaboraDictionaries($body['collabora_dictionaries']);

                if (isset($body['delete_borg_backup_host_location']))
                    $config->deleteBorgLocation();

                ConfigurationManager::storeConfigFile($config);
            }

            return $response->withStatus(201)->withHeader('Location', '/');
        } catch (InvalidSettingConfigurationException $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
    }
}
