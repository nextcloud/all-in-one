<?php

declare(strict_types=1);

namespace OCA\NcAioTools\AppInfo;

use OCA\NcAioTools\Listener\BrandingAssetsListener;
use OCA\NcAioTools\Listener\EnforceUserLimitListener;
use OCA\NcAioTools\Listener\UserCountAuditListener;
use OCA\NcAioTools\Listener\UsersPageAssetsListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Http\Events\BeforeLoginTemplateRenderedEvent;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\User\Events\BeforeUserCreatedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'nc_aio_tools';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforeUserCreatedEvent::class, EnforceUserLimitListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, UsersPageAssetsListener::class);
		// Global theme layer -- registered separately from the users-page listener
		// because it must also run when logged out, for the login screen.
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, BrandingAssetsListener::class);
		// The login screen renders through the guest template, which dispatches its
		// own event -- without this the login page stays stock Nextcloud.
		$context->registerEventListener(BeforeLoginTemplateRenderedEvent::class, BrandingAssetsListener::class);
		$context->registerEventListener(UserCreatedEvent::class, UserCountAuditListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserCountAuditListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
