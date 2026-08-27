<?php

declare(strict_types=1);

namespace OCA\NcAioTools\Listener;

use OCA\NcAioTools\AppInfo\Application;
use OCA\NcAioTools\Service\UserLimitService;
use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IRequest;
use OCP\Util;

/**
 * @template-implements IEventListener<BeforeTemplateRenderedEvent>
 *
 * There's no dedicated "Settings > Users page" event, so this filters the
 * generic page-render event down to that one page by request path -- the
 * same technique other Nextcloud apps use to inject admin-page-only assets.
 */
class UsersPageAssetsListener implements IEventListener {
	public function __construct(
		private IRequest $request,
		private IInitialState $initialState,
		private UserLimitService $limitService,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeTemplateRenderedEvent || !$event->isLoggedIn()) {
			return;
		}

		if (strpos($this->request->getPathInfo(), '/settings/users') === false) {
			return;
		}

		// Two values rather than a sentinel: PHP_INT_MAX does not survive a
		// round trip through JSON/JS number precision, so the unlimited case
		// travels as its own boolean instead of something JS has to detect.
		$this->initialState->provideInitialState('freeUsers', $this->limitService->getFreeSlots());
		$this->initialState->provideInitialState('unlimited', $this->limitService->isUnlimited());

		Util::addScript(Application::APP_ID, 'users-free-count');
		Util::addStyle(Application::APP_ID, 'users-free-count');
	}
}
