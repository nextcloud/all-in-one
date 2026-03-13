<?php
declare(strict_types=1);

namespace AIO\Controller;

use AIO\Translation\TranslationManager;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

readonly class LanguageController
{
    public function __construct(
        private TranslationManager $translationManager,
    ) {
    }

    public function SetLanguage(Request $request, Response $response, array $args): Response
    {
        /** @var array<string, mixed>|null $body */
        $body = $request->getParsedBody();

        $language = '';
        if (is_array($body) && isset($body['language']) && is_string($body['language'])) {
            $language = $body['language'];
        }

        $supported = $this->translationManager->getSupportedLanguages();

        if ($language === '' || !in_array($language, $supported, true)) {
            $response->getBody()->write('Unsupported language.');
            return $response->withStatus(422);
        }

        $_SESSION['aio_user_language'] = $language;

        return $response->withStatus(204);
    }
}