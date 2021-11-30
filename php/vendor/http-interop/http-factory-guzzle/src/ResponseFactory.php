<?php

namespace Http\Factory\Guzzle;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseFactory implements ResponseFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return new Response($code, [], null, '1.1', $reasonPhrase);
    }
}
