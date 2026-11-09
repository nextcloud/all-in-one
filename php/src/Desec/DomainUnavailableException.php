<?php
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

namespace AIO\Desec;

/**
 * Thrown when deSEC rejects an account registration because the requested domain
 * name is unavailable. No account is created in that case, so the caller may retry
 * with a different name.
 */
class DomainUnavailableException extends \Exception {
}
