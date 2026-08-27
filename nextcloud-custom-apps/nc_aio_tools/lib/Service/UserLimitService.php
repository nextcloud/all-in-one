<?php

declare(strict_types=1);

namespace OCA\NcAioTools\Service;

use OCP\IUserManager;

class UserLimitService {
	public const ENV_VAR = 'NC_USER_LIMIT';

	/**
	 * Sentinel for "no limit configured". A scalar rather than null so every
	 * caller can do plain arithmetic and comparisons without a null branch --
	 * an unset limit simply behaves as a limit nobody can reach.
	 */
	public const UNLIMITED = PHP_INT_MAX;

	public function __construct(
		private IUserManager $userManager,
	) {
	}

	/**
	 * Reads the limit fresh from the environment on every call, by design --
	 * so a docker-compose restart with a new NC_USER_LIMIT value takes effect
	 * immediately, with no cached config to invalidate.
	 *
	 * Returns self::UNLIMITED when NC_USER_LIMIT is unset, blank, or not a
	 * non-negative integer.
	 */
	public function getLimit(): int {
		$raw = getenv(self::ENV_VAR);
		if ($raw === false || trim($raw) === '' || !ctype_digit(trim($raw))) {
			return self::UNLIMITED;
		}
		return (int)trim($raw);
	}

	public function isUnlimited(): bool {
		return $this->getLimit() === self::UNLIMITED;
	}

	public function getUserCount(): int {
		return array_sum($this->userManager->countUsers());
	}

	/**
	 * Remaining slots, floored at 0. With no limit configured this is
	 * self::UNLIMITED minus the account count, which stays astronomically
	 * large -- call isUnlimited() when the distinction matters for display.
	 */
	public function getFreeSlots(): int {
		return max(0, $this->getLimit() - $this->getUserCount());
	}
}
