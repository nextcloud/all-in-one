<?php
declare(strict_types=1);

namespace AIO\Controller;

use AIO\Desec\AlreadyRegisteredException;
use AIO\Desec\DesecManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

readonly class DesecController {
    public function __construct(
        private DesecManager $desecManager,
    ) {
    }

    public function Register(Request $request, Response $response, array $args): Response {
        $email    = (string)($request->getParsedBody()['desec_email']    ?? '');
        $slug     = (string)($request->getParsedBody()['desec_slug']     ?? '');
        $password = (string)($request->getParsedBody()['desec_password'] ?? '');

        try {
            $this->desecManager->register($email, $slug, $password);
        } catch (AlreadyRegisteredException $ex) {
            $_SESSION['desec_show_password'] = true;
            $_SESSION['desec_prefill_email'] = $ex->email;
            $_SESSION['desec_error']         = $ex->getMessage();
        } catch (\Exception $ex) {
            $_SESSION['desec_error'] = $ex->getMessage();
        }

        // Post/Redirect/Get: always redirect back to the containers page.
        // The browser follows the Location header and issues a fresh GET,
        // which prevents form-resubmission on reload.
        return $response->withStatus(303)->withHeader('Location', '../../containers');
    }
}
