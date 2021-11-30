<?php

namespace AIO\Middleware;

use AIO\Auth\AuthManager;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware
{
    private AuthManager $authManager;

    public function __construct(AuthManager $authManager) {
        $this->authManager = $authManager;
    }

    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $publicRoutes = [
            '/api/auth/login',
            '/api/auth/getlogin',
            '/login',
            '/setup',
            '/',
        ];

        if(!in_array($request->getUri()->getPath(), $publicRoutes)) {
            if(!$this->authManager->IsAuthenticated()) {
                $response = new Response();
                return $response
                    ->withHeader('Location', '/')
                    ->withStatus(302);
            }
        }

        $response = $handler->handle($request);
        return $response;
    }
}
