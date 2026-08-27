<?php

declare(strict_types=1);

namespace OCA\NcAioTools\AppInfo;

use OCA\NcAioTools\Listener\EnforceUserLimitListener;
use OCA\NcAioTools\Listener\UserCountAuditListener;
use OCA\NcAioTools\Listener\UsersPageAssetsListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
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
		$context->registerEventListener(UserCreatedEvent::class, UserCountAuditListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserCountAuditListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
