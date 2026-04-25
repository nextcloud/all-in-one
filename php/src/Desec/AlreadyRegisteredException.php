<?php
declare(strict_types=1);

namespace AIO\Desec;

/**
 * Thrown when a deSEC account registration attempt fails because the email address
 * is already associated with an existing account.  The controller catches this to
 * redirect the user back to the registration form with the password field revealed.
 */
class AlreadyRegisteredException extends \Exception {
    public function __construct(
        public readonly string $email,
    ) {
        parent::__construct(
            'This email address is already registered at deSEC. '
            . 'If this is your account, please enter your deSEC password in the password field and try again.',
        );
    }
}
