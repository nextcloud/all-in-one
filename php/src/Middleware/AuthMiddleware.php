<?php

namespace AIO\Middleware;

use AIO\Auth\AuthManager;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

readonly class AuthMiddleware {
    public function __construct(
        private AuthManager $authManager
    ) {
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
                $status = 302;

                // Check the url of the request: split the string by '/' and count the number of elements
                // Note that the path that gets to this middleware is not aware of any base path managed by a reverse proxy, so if the url is 'https://example.com/AIO/somepage', the path will be 'https://mastercontainer/somepage'
                if (count(explode('/', $request->getUri()->getPath())) < 2) {
                    // If there are less than 2 elements it means we are somewhere in the root folder (no '/', so no subfolder), so we redirect to the same folder level to offload the redirection to the appropriate page to 'index.php' (specifically, once in the root level the login page will be loaded since we are not authenticated)
                    $location = '.';
                } else {
                    // If there are 2 or more elements it means we are in a subfolder, so we need to go back to the root folder
                    // In the best case we need to go back by 1 level only
                    $location = '..';
                    // In the worst case we need to go back by n levels, where n is the number of elements - 2 (the first element is not a folder, the second element is already accounted for by the initial '..')
                    for ($i = 1; $i < count(explode('/', $request->getUri()->getPath())) - 2; $i++) {
                        // For each extra level we need to go back by another level
                        $location = $location . '/..';
                    }
                }

                $headers = ['Location' => $location];
                $response = new Response($status, $headers);
                return $response;
            }
        }

        $response = $handler->handle($request);
        return $response;
    }
}
