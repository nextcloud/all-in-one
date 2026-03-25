<?php
declare(strict_types=1);

namespace AIO\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

readonly class SecurityHeadersMiddleware {
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        return $response
            ->withHeader('Content-Security-Policy', "default-src 'self'; base-uri 'self'; worker-src 'none'; object-src 'none'; upgrade-insecure-requests;")
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->withHeader('X-DNS-Prefetch-Control', 'off')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
