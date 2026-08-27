<?php

declare(strict_types=1);

namespace OCA\NcAioTools\Listener;

use OCA\NcAioTools\Service\UserLimitService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\HintException;
use OCP\User\Events\BeforeUserCreatedEvent;

/**
 * @template-implements IEventListener<BeforeUserCreatedEvent>
 *
 * BeforeUserCreatedEvent fires from IUserManager::createUser(), the single
 * code path shared by the web UI, `occ user:add`, and the provisioning API --
 * so throwing here blocks account creation everywhere at once.
 */
class EnforceUserLimitListener implements IEventListener {
	public function __construct(
		private UserLimitService $limitService,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof BeforeUserCreatedEvent) {
			return;
		}

		if ($this->limitService->isUnlimited()) {
			return;
		}

		$limit = $this->limitService->getLimit();
		if ($this->limitService->getUserCount() >= $limit) {
			throw new HintException(
				sprintf(
					'User creation blocked: limit of %d users reached (attempted user "%s").',
					$limit,
					$event->getUid()
				),
				sprintf('Cannot create user: the maximum of %d users has been reached.', $limit)
			);
		}
	}
}
