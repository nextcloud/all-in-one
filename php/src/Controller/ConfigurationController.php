<?php

namespace AIO\Controller;

use AIO\ContainerDefinitionFetcher;
use AIO\Data\ConfigurationManager;
use AIO\Data\InvalidSettingConfigurationException;
use AIO\Docker\DockerActionManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

readonly class ConfigurationController {
    public function __construct(
        private ConfigurationManager $configurationManager
    ) {
    }

    public function SetConfig(Request $request, Response $response, array $args): Response {
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

            if (isset($request->getParsedBody()['borg_backup_host_location']) || isset($request->getParsedBody()['borg_remote_repo'])) {
                $location = $request->getParsedBody()['borg_backup_host_location'] ?? '';
                $borgRemoteRepo = $request->getParsedBody()['borg_remote_repo'] ?? '';
                $borgRemotePath = $request->getParsedBody()['borg_remote_path'] ?? '';
                $this->configurationManager->SetBorgLocationVars($location, $borgRemoteRepo, $borgRemotePath);
            }

            if (isset($request->getParsedBody()['borg_restore_host_location']) || isset($request->getParsedBody()['borg_restore_remote_repo']) || isset($request->getParsedBody()['borg_restore_password'])) {
                $restoreLocation = $request->getParsedBody()['borg_restore_host_location'] ?? '';
                $borgRemoteRepo = $request->getParsedBody()['borg_restore_remote_repo'] ?? '';
                $borgPassword = $request->getParsedBody()['borg_restore_password'] ?? '';
                $borgRemotePath = $request->getParsedBody()['borg_remote_path'] ?? '';
                $this->configurationManager->SetBorgRestoreLocationVarsAndPassword($restoreLocation, $borgRemoteRepo, $borgPassword, $borgRemotePath);
            }

            if (isset($request->getParsedBody()['daily_backup_time'])) {
                if (isset($request->getParsedBody()['automatic_updates'])) {
                    $enableAutomaticUpdates = true;
                } else {
                    $enableAutomaticUpdates = false;
                }
                if (isset($request->getParsedBody()['success_notification'])) {
                    $successNotification = true;
                } else {
                    $successNotification = false;
                }
                $dailyBackupTime = $request->getParsedBody()['daily_backup_time'] ?? '';
                $this->configurationManager->SetDailyBackupTime($dailyBackupTime, $enableAutomaticUpdates, $successNotification);
            }

            if (isset($request->getParsedBody()['delete_daily_backup_time'])) {
                $this->configurationManager->DeleteDailyBackupTime();
            }

            if (isset($request->getParsedBody()['additional_backup_directories'])) {
                $additionalBackupDirectories = $request->getParsedBody()['additional_backup_directories'] ?? '';
                $this->configurationManager->SetAdditionalBackupDirectories($additionalBackupDirectories);
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
                if (isset($request->getParsedBody()['talk-recording'])) {
                    $this->configurationManager->SetTalkRecordingEnabledState(1);
                } else {
                    $this->configurationManager->SetTalkRecordingEnabledState(0);
                }
                if (isset($request->getParsedBody()['imaginary'])) {
                    $this->configurationManager->SetImaginaryEnabledState(1);
                } else {
                    $this->configurationManager->SetImaginaryEnabledState(0);
                }
                if (isset($request->getParsedBody()['fulltextsearch'])) {
                    $this->configurationManager->SetFulltextsearchEnabledState(1);
                } else {
                    $this->configurationManager->SetFulltextsearchEnabledState(0);
                }
                if (isset($request->getParsedBody()['docker-socket-proxy'])) {
                    $this->configurationManager->SetDockerSocketProxyEnabledState(1);
                } else {
                    $this->configurationManager->SetDockerSocketProxyEnabledState(0);
                }
                if (isset($request->getParsedBody()['whiteboard'])) {
                    $this->configurationManager->SetWhiteboardEnabledState(1);
                } else {
                    $this->configurationManager->SetWhiteboardEnabledState(0);
                }
            }

            if (isset($request->getParsedBody()['community-form'])) {
                $cc = $this->configurationManager->listAvailableCommunityContainers();
                $enabledCC = [];
                /**
                 * @psalm-suppress PossiblyNullIterator
                 */
                foreach ($request->getParsedBody() as $item) {
                    if (array_key_exists($item , $cc)) {
                        $enabledCC[] = $item;
                    }
                }
                $this->configurationManager->SetEnabledCommunityContainers($enabledCC);
            }

            if (isset($request->getParsedBody()['delete_collabora_dictionaries'])) {
                $this->configurationManager->DeleteCollaboraDictionaries();
            }

            if (isset($request->getParsedBody()['collabora_dictionaries'])) {
                $collaboraDictionaries = $request->getParsedBody()['collabora_dictionaries'] ?? '';
                $this->configurationManager->SetCollaboraDictionaries($collaboraDictionaries);
            }

            if (isset($request->getParsedBody()['delete_collabora_additional_options'])) {
                $this->configurationManager->DeleteAdditionalCollaboraOptions();
            }

            if (isset($request->getParsedBody()['collabora_additional_options'])) {
                $additionalCollaboraOptions = $request->getParsedBody()['collabora_additional_options'] ?? '';
                $this->configurationManager->SetAdditionalCollaboraOptions($additionalCollaboraOptions);
            }

            if (isset($request->getParsedBody()['delete_borg_backup_location_vars'])) {
                $this->configurationManager->DeleteBorgBackupLocationVars();
            }

            return $response->withStatus(201)->withHeader('Location', '/');
        } catch (InvalidSettingConfigurationException $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
    }
}
