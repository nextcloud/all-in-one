<?php
declare(strict_types=1);

namespace AIO\Auth;

class RateLimiter {
    public const int MAX_ATTEMPTS = 10;
    public const int WINDOW_SECONDS = 900; // 15 minutes

    /**
     * Returns true when the IP has exceeded the maximum number of failed login
     * attempts within the current time window and should be blocked.
     */
    public function isLimitReached(string $ip): bool {
        $attempts = apcu_fetch($this->getKey($ip));
        return $attempts !== false && (int)$attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Records a failed login attempt for the given IP.
     * Uses a 15-minute sliding window: the first failure starts the window and
     * subsequent failures within that window are counted together.
     */
    public function recordFailedAttempt(string $ip): void {
        $key = $this->getKey($ip);
        // apcu_add only stores when the key does not yet exist.
        // If it already exists (returns false), we increment the existing counter.
        if (!apcu_add($key, 1, self::WINDOW_SECONDS)) {
            apcu_inc($key);
        }
    }

    /**
     * Clears the failed-attempt counter for the given IP, e.g. after a
     * successful login.
     */
    public function resetAttempts(string $ip): void {
        apcu_delete($this->getKey($ip));
    }

    private function getKey(string $ip): string {
        return 'login_attempts_' . hash('sha256', $ip);
    }
}
