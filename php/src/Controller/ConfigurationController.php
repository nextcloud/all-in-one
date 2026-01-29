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
                $skipDomainValidation = isset($request->getParsedBody()['skip_domain_validation']);
                $this->configurationManager->setDomain($domain, $skipDomainValidation);
            }

            if (isset($request->getParsedBody()['current-master-password']) || isset($request->getParsedBody()['new-master-password'])) {
                $currentMasterPassword = $request->getParsedBody()['current-master-password'] ?? '';
                $newMasterPassword = $request->getParsedBody()['new-master-password'] ?? '';
                $this->configurationManager->changeMasterPassword($currentMasterPassword, $newMasterPassword);
            }

            if (isset($request->getParsedBody()['borg_backup_host_location']) || isset($request->getParsedBody()['borg_remote_repo'])) {
                $location = $request->getParsedBody()['borg_backup_host_location'] ?? '';
                $borgRemoteRepo = $request->getParsedBody()['borg_remote_repo'] ?? '';
                $this->configurationManager->setBorgLocationVars($location, $borgRemoteRepo);
            }

            if (isset($request->getParsedBody()['borg_restore_host_location']) || isset($request->getParsedBody()['borg_restore_remote_repo']) || isset($request->getParsedBody()['borg_restore_password'])) {
                $restoreLocation = $request->getParsedBody()['borg_restore_host_location'] ?? '';
                $borgRemoteRepo = $request->getParsedBody()['borg_restore_remote_repo'] ?? '';
                $borgPassword = $request->getParsedBody()['borg_restore_password'] ?? '';
                $this->configurationManager->setBorgRestoreLocationVarsAndPassword($restoreLocation, $borgRemoteRepo, $borgPassword);
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
                $this->configurationManager->setDailyBackupTime($dailyBackupTime, $enableAutomaticUpdates, $successNotification);
            }

            if (isset($request->getParsedBody()['delete_daily_backup_time'])) {
                $this->configurationManager->deleteDailyBackupTime();
            }

            if (isset($request->getParsedBody()['additional_backup_directories'])) {
                $additionalBackupDirectories = $request->getParsedBody()['additional_backup_directories'] ?? '';
                $this->configurationManager->setAdditionalBackupDirectories($additionalBackupDirectories);
            }

            if (isset($request->getParsedBody()['delete_timezone'])) {
                $this->configurationManager->deleteTimezone();
            }

            if (isset($request->getParsedBody()['timezone'])) {
                $timezone = $request->getParsedBody()['timezone'] ?? '';
                $this->configurationManager->timezone = $timezone;
            }

            if (isset($request->getParsedBody()['options-form'])) {
                $officeSuiteChoice = $request->getParsedBody()['office_suite_choice'] ?? '';
                
                if ($officeSuiteChoice === 'collabora') {
                    $this->configurationManager->isCollaboraEnabled = true;
                    $this->configurationManager->isOnlyofficeEnabled = false;
                } elseif ($officeSuiteChoice === 'onlyoffice') {
                    $this->configurationManager->isCollaboraEnabled = false;
                    $this->configurationManager->isOnlyofficeEnabled = true;
                } else {
                    $this->configurationManager->isCollaboraEnabled = false;
                    $this->configurationManager->isOnlyofficeEnabled = false;
                }
                $this->configurationManager->isClamavEnabled = isset($request->getParsedBody()['clamav']);
                $this->configurationManager->isTalkEnabled = isset($request->getParsedBody()['talk']);
                $this->configurationManager->isTalkRecordingEnabled = isset($request->getParsedBody()['talk-recording']);
                $this->configurationManager->isImaginaryEnabled = isset($request->getParsedBody()['imaginary']);
                $this->configurationManager->isFulltextsearchEnabled = isset($request->getParsedBody()['fulltextsearch']);
                $this->configurationManager->isDockerSocketProxyEnabled = isset($request->getParsedBody()['docker-socket-proxy']);
                $this->configurationManager->isWhiteboardEnabled = isset($request->getParsedBody()['whiteboard']);
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
                $this->configurationManager->aioCommunityContainers = $enabledCC;
            }

            if (isset($request->getParsedBody()['delete_collabora_dictionaries'])) {
                $this->configurationManager->deleteCollaboraDictionaries();
            }

            if (isset($request->getParsedBody()['collabora_dictionaries'])) {
                $collaboraDictionaries = $request->getParsedBody()['collabora_dictionaries'] ?? '';
                $this->configurationManager->collaboraDictionaries = $collaboraDictionaries;
            }

            if (isset($request->getParsedBody()['delete_collabora_additional_options'])) {
                $this->configurationManager->deleteAdditionalCollaboraOptions();
            }

            if (isset($request->getParsedBody()['collabora_additional_options'])) {
                $additionalCollaboraOptions = $request->getParsedBody()['collabora_additional_options'] ?? '';
                $this->configurationManager->collaboraAdditionalOptions = $additionalCollaboraOptions;
            }

            if (isset($request->getParsedBody()['delete_borg_backup_location_vars'])) {
                $this->configurationManager->deleteBorgBackupLocationItems();
            }

            return $response->withStatus(201)->withHeader('Location', '.');
        } catch (InvalidSettingConfigurationException $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
    }
}
