<?php

declare(strict_types=1);

namespace OCA\NcAioTools\Listener;

use OCA\NcAioTools\Service\UserLimitService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<UserCreatedEvent|UserDeletedEvent>
 *
 * Registered for both events so the free-slot count is logged whenever it
 * changes -- enforcement itself only needs BeforeUserCreatedEvent (see
 * EnforceUserLimitListener), but the app should still observably track
 * additions and removals rather than silently recomputing on next page load.
 */
class UserCountAuditListener implements IEventListener {
	public function __construct(
		private UserLimitService $limitService,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserCreatedEvent) {
			$this->log('created', $event->getUser()->getUID());
		} elseif ($event instanceof UserDeletedEvent) {
			$this->log('deleted', $event->getUser()->getUID());
		}
	}

	private function log(string $action, string $uid): void {
		$this->logger->info(
			sprintf(
				'nc_aio_tools: user "%s" %s, %s free slot(s) remaining',
				$uid,
				$action,
				$this->limitService->isUnlimited()
					? 'unlimited'
					: (string)$this->limitService->getFreeSlots()
			),
			['app' => 'nc_aio_tools']
		);
	}
}
