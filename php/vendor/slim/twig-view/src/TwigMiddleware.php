<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @license   https://github.com/slimphp/Twig-View/blob/master/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace Slim\Views;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Slim\App;
use Slim\Interfaces\RouteParserInterface;

class TwigMiddleware implements MiddlewareInterface
{
    /**
     * @var Twig
     */
    protected $twig;

    /**
     * @var RouteParserInterface
     */
    protected $routeParser;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string|null
     */
    protected $attributeName;

    /**
     * @param App    $app
     * @param string $containerKey
     *
     * @return TwigMiddleware
     */
    public static function createFromContainer(App $app, string $containerKey = 'view'): self
    {
        $container = $app->getContainer();
        if ($container === null) {
            throw new RuntimeException('The app does not have a container.');
        }
        if (!$container->has($containerKey)) {
            throw new RuntimeException(
                "The specified container key does not exist: $containerKey"
            );
        }

        $twig = $container->get($containerKey);
        if (!($twig instanceof Twig)) {
            throw new RuntimeException(
                "Twig instance could not be resolved via container key: $containerKey"
            );
        }

        return new self(
            $twig,
            $app->getRouteCollector()->getRouteParser(),
            $app->getBasePath()
        );
    }

    /**
     * @param App    $app
     * @param Twig   $twig
     * @param string $attributeName
     *
     * @return TwigMiddleware
     */
    public static function create(App $app, Twig $twig, string $attributeName = 'view'): self
    {
        return new self(
            $twig,
            $app->getRouteCollector()->getRouteParser(),
            $app->getBasePath(),
            $attributeName
        );
    }

    /**
     * @param Twig                 $twig
     * @param RouteParserInterface $routeParser
     * @param string               $basePath
     * @param string|null          $attributeName
     */
    public function __construct(
        Twig $twig,
        RouteParserInterface $routeParser,
        string $basePath = '',
        ?string $attributeName = null
    ) {
        $this->twig = $twig;
        $this->routeParser = $routeParser;
        $this->basePath = $basePath;
        $this->attributeName = $attributeName;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $runtimeLoader = new TwigRuntimeLoader($this->routeParser, $request->getUri(), $this->basePath);
        $this->twig->addRuntimeLoader($runtimeLoader);

        $extension = new TwigExtension();
        $this->twig->addExtension($extension);

        if ($this->attributeName !== null) {
            $request = $request->withAttribute($this->attributeName, $this->twig);
        }

        return $handler->handle($request);
    }
}
