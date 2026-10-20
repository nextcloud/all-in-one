<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\AllInOne\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\RedirectResponse;

class LoginController extends Controller {
	// The signature is created here, so it doesn't expire while the admin settings page is open.
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'GET', url: '/login')]
	public function redirect(): RedirectResponse {
		return new RedirectResponse(self::buildAioLoginUrl());
	}

	public static function buildAioLoginUrl(): string {
		$privateKey = getenv('AIO_UNBLOCK_LOGIN_PRIVATE_KEY');
		if (is_string($privateKey) && $privateKey !== '') {
			$query = '?signature=' . self::signTimestamp(time(), $privateKey);
		} else {
			$query = '?token=' . urlencode(getenv('AIO_TOKEN'));
		}
		return 'https://' . getenv('AIO_URL') . '/api/auth/getlogin' . $query;
	}

	/**
	 * @return string the timestamp signed with the private key, url-safe base64 encoded
	 */
	private static function signTimestamp(int $timestamp, string $privateKeyBase64): string {
		$privateKeyBin = sodium_base642bin($privateKeyBase64, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
		$signatureBin = sodium_crypto_sign((string)$timestamp, $privateKeyBin);
		return sodium_bin2base64($signatureBin, SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
	}
}
