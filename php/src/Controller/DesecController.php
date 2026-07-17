<?php
declare(strict_types=1);

namespace AIO\Controller;

use AIO\Desec\DesecManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

readonly class DesecController {
    public function __construct(
        private DesecManager $desecManager,
    ) {
    }

    public function Register(Request $request, Response $response, array $args): Response {
        try {
            $email    = (string)($request->getParsedBody()['desec_email']    ?? '');
            $slug     = (string)($request->getParsedBody()['desec_slug']     ?? '');
            $password = (string)($request->getParsedBody()['desec_password'] ?? '');
            // register() returns false when a new account was created and we are now awaiting
            // the user's email verification. That is a normal state transition, not an error.
            // The form is submitted from the /desec modal view (an iframe), so redirect back to
            // that same view (201 + Location): the iframe re-renders the next step of the flow
            // (awaiting verification, or the fully-registered success page, which then reloads
            // the parent containers page). Re-rendering /desec — rather than the whole
            // containers page — is what keeps the multi-step flow inside the modal.
            $this->desecManager->register($email, $slug, $password);
            return $response->withStatus(201)->withHeader('Location', 'desec');
        } catch (\Exception $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
    }
}
