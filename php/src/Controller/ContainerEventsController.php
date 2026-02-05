<?php

namespace AIO\Controller;

use AIO\Container\ContainerState;
use AIO\ContainerDefinitionFetcher;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use AIO\Data\ConfigurationManager;
use AIO\Data\DataConst;
use AIO\Data\ContainerEventsLog;

readonly class ContainerEventsController {
    public function __construct(
        private ContainerDefinitionFetcher    $containerDefinitionFetcher,
        private ConfigurationManager $configurationManager
    ) {
    }

    public function getEventsLog(Request $request, Response $response, array $args) : Response
    {
        $eventsLog = new ContainerEventsLog();
        $currentMtime = $eventsLog->lastModified();
        if ($currentMtime === false) {
            error_log("Error: Could not get mtime of file '{$eventsLog->filename}', something is wrong. Responding with status 502.");
            return $response->withStatus(502);
        }
        $currentMtimeHash = md5($currentMtime);
        $knownMtimeHash = $request->getHeaderLine('If-None-Match');
        if ($knownMtimeHash === $currentMtimeHash) {
            return $response->withStatus(304);
        }

        return $response
            ->withStatus(200)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Content-Disposition', 'inline')
            ->withHeader('Cache-Control', 'no-cache')
            ->withHeader('Etag', $currentMtimeHash)
            ->withBody(\GuzzleHttp\Psr7\Utils::streamFor(fopen($eventsLog->filename, 'rb')));
    }
}
