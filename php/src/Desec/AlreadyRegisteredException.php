<?php
declare(strict_types=1);

namespace AIO\Desec;

/**
 * Thrown when the deSEC API reports that the supplied email address is already
 * associated with an existing account (HTTP 400 with an "email" error field).
 */
class AlreadyRegisteredException extends \Exception {
    public function __construct(string $email) {
        parent::__construct(
            'This email address is already registered at deSEC. '
            . 'If this is your account, please enter your deSEC password in the password field and try again.',
        );
    }
}
