<?php

declare(strict_types=1);

namespace OCA\UserLimitGuard\Service;

use OCP\IUserManager;

class UserLimitService {
	public const ENV_VAR = 'NC_USER_LIMIT';

	public function __construct(
		private IUserManager $userManager,
	) {
	}

	/**
	 * Reads the limit fresh from the environment on every call, by design --
	 * so a docker-compose restart with a new NC_USER_LIMIT value takes effect
	 * immediately, with no cached config to invalidate.
	 */
	public function getLimit(): ?int {
		$raw = getenv(self::ENV_VAR);
		if ($raw === false || trim($raw) === '' || !ctype_digit(trim($raw))) {
			return null;
		}
		return (int)trim($raw);
	}

	public function getUserCount(): int {
		return array_sum($this->userManager->countUsers());
	}

	/** Null means unlimited (NC_USER_LIMIT unset or invalid). */
	public function getFreeSlots(): ?int {
		$limit = $this->getLimit();
		if ($limit === null) {
			return null;
		}
		return max(0, $limit - $this->getUserCount());
	}
}
