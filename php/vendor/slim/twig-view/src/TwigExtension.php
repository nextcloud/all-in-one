<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @license   https://github.com/slimphp/Twig-View/blob/master/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace Slim\Views;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'slim';
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('url_for', [TwigRuntimeExtension::class, 'urlFor']),
            new TwigFunction('full_url_for', [TwigRuntimeExtension::class, 'fullUrlFor']),
            new TwigFunction('is_current_url', [TwigRuntimeExtension::class, 'isCurrentUrl']),
            new TwigFunction('current_url', [TwigRuntimeExtension::class, 'getCurrentUrl']),
            new TwigFunction('get_uri', [TwigRuntimeExtension::class, 'getUri']),
            new TwigFunction('base_path', [TwigRuntimeExtension::class, 'getBasePath']),
        ];
    }
}
