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
            // the user's email verification. That is a normal state transition, not an error:
            // reload the page (201 + Location) so the awaiting-verification UI renders and
            // explains the next step, exactly like the fully-registered success path.
            $this->desecManager->register($email, $slug, $password);
            return $response->withStatus(201)->withHeader('Location', '.');
        } catch (\Exception $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
    }
}
