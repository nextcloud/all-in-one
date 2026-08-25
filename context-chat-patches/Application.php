<?php
//
// LOCAL PATCH of context_chat 5.4.0's lib/AppInfo/Application.php, bind-mounted over
// the copy in the nextcloud_aio_nextcloud volume by docker-compose.yml. Verbatim
// upstream except the block marked "LOCAL PATCH" below.
//
// Upstream sha256 this was forked from:
//   4ced3b13fd5c0e58ac93a24a61b27d7e5414e61bd4890bb7b7a83b90667571fe
// Re-diff against the app's own copy after any context_chat update:
//   docker exec <nc-container> cat \
//     /var/www/html/custom_apps/context_chat/lib/AppInfo/Application.php | diff - this-file
//

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\ContextChat\AppInfo;

use OCA\ContextChat\Listener\AddMissingIndicesListenerFsEvents;
use OCA\ContextChat\Listener\AppDisableListener;
use OCA\ContextChat\Listener\FileListener;
use OCA\ContextChat\Listener\ShareListener;
use OCA\ContextChat\Listener\UserDeletedListener;
use OCP\App\Events\AppDisableEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\Files\Cache\CacheEntryInsertedEvent;
use OCP\Files\Events\Node\BeforeNodeDeletedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeRenamedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Events\NodeRemovedFromCache;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\ShareDeletedEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {

	public const APP_ID = 'context_chat';

	public const CC_DEFAULT_REQUEST_TIMEOUT = 60 * 30; // 30 mins
	// max size per file + max size of the batch of files to be embedded in a single request
	public const CC_MAX_SIZE = 100 * 1024 * 1024; // 100MB
	// public const CC_MAX_FILES = 25;
	public const MIMETYPES = [
		'text/plain',
		'text/markdown',
		'application/json',
		'application/pdf',
		'text/csv',
		'application/epub+zip',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.ms-powerpoint',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.oasis.opendocument.spreadsheet',
		'application/vnd.ms-excel.sheet.macroEnabled.12',
		// LOCAL PATCH: legacy binary .xls (BIFF). Upstream context_chat 5.4.0 omits it
		// here, so .xls files were never even queued for indexing. Paired with the
		// matching mimetype added to context_chat_backend's doc_loader.py loader map --
		// BOTH gates must list it: this one decides what Nextcloud sends, that one
		// decides how the backend parses it. See context-chat-patches/doc_loader.py.
		'application/vnd.ms-excel',
		'application/vnd.oasis.opendocument.text',
		'text/rtf',
		'text/x-rst',
		'application/xml',
		'message/rfc822',
		'application/vnd.ms-outlook',
		'text/org',
	];

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(AddMissingIndicesEvent::class, AddMissingIndicesListenerFsEvents::class);
		$context->registerEventListener(BeforeNodeDeletedEvent::class, FileListener::class);
		$context->registerEventListener(NodeCreatedEvent::class, FileListener::class);
		$context->registerEventListener(CacheEntryInsertedEvent::class, FileListener::class);
		$context->registerEventListener(NodeRenamedEvent::class, FileListener::class);
		$context->registerEventListener(NodeRemovedFromCache::class, FileListener::class);
		$context->registerEventListener(NodeWrittenEvent::class, FileListener::class);
		$context->registerEventListener(AppDisableEvent::class, AppDisableListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
		// These events were added mid-way through NC 30, 31
		if (class_exists('OCP\Files\Config\Event\UserMountAddedEvent')) {
			$context->registerEventListener('OCP\Files\Config\Event\UserMountAddedEvent', FileListener::class);
			$context->registerEventListener('OCP\Files\Config\Event\UserMountRemovedEvent', FileListener::class);
			// it is not fired as of now, Added and Removed events are fired instead in that order
			// $context->registerEventListener('OCP\Files\Config\Event\UserMountUpdatedEvent', FileListener::class);
		} else {
			$context->registerEventListener(ShareCreatedEvent::class, ShareListener::class);
			$context->registerEventListener(ShareDeletedEvent::class, ShareListener::class);
		}
	}

	public function boot(IBootContext $context): void {
	}
}
