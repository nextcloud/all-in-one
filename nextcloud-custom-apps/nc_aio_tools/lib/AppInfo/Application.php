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
use OCP\INavigationManager;
use OCP\IURLGenerator;
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
		// The `assistant` app ships a real standalone chat page (session list,
		// ChattyLLM messages) but declares no <navigations> entry of its own, so
		// it is reachable only via the header's sparkle icon, never the app-menu.
		// Screen 2h's "AI" tab expects it in the row alongside the other apps, so
		// add it here rather than patching the vendored app.
		$context->injectFn(function (INavigationManager $navigationManager, IURLGenerator $urlGenerator) {
			$navigationManager->add(function () use ($urlGenerator) {
				return [
					'id' => 'assistant',
					'order' => 101,
					// Hardcoded, not linkToRoute()/linkTo(): linkToRoute() on the
					// app's only GET route ('assistant#getAssistantStandalonePage')
					// resolved to an empty string, and linkTo() resolves against
					// the filesystem install path (custom_apps/assistant/, a
					// 404) rather than the routed URL. Every sibling entry in the
					// live nav uses this same bare /apps/{id}/ form (see e.g.
					// "files": "/apps/files/"), so it matches house style.
					'href' => $urlGenerator->getWebroot() . '/apps/assistant/',
					'icon' => $urlGenerator->imagePath('assistant', 'app.svg'),
					'name' => 'AI',
				];
			});
		});
	}
}
