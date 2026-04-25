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
            $email = (string)($request->getParsedBody()['desec_email'] ?? '');
            $slug  = (string)($request->getParsedBody()['desec_slug'] ?? '');
            $this->desecManager->register($email, $slug);
            return $response->withStatus(201)->withHeader('Location', '.');
        } catch (\Exception $ex) {
            $response->getBody()->write($ex->getMessage());
            return $response->withStatus(422);
        }
    }
}
