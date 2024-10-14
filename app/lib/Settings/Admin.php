<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\AllInOne\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\L10N\IFactory;
use OCP\Settings\ISettings;

class Admin implements ISettings {
	/** @var IConfig */
	private $config;
	/** @var IDateTimeFormatter */
	private $dateTimeFormatter;
	/** @var IFactory */
	private $l10nFactory;

	public function __construct(
		IConfig $config,
		IDateTimeFormatter $dateTimeFormatter,
		IFactory $l10nFactory
	) {
		$this->config = $config;
		$this->dateTimeFormatter = $dateTimeFormatter;
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm(): TemplateResponse {
		$lastUpdateCheckTimestamp = $this->config->getAppValue('core', 'lastupdatedat');
		$lastUpdateCheck = $this->dateTimeFormatter->formatDateTime($lastUpdateCheckTimestamp);

		$token = urlencode(getenv('AIO_TOKEN'));
		$params = [
			'AIOLoginUrl' => 'https://' . getenv('AIO_URL') . '/api/auth/getlogin' . '?token=' . $token,
		];

		return new TemplateResponse('nextcloud-aio', 'admin', $params, '');
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection(): string {
		return 'overview';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority(): int {
		return 0;
	}
}
