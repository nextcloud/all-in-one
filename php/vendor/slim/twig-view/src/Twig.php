<?php
/**
 * Slim Framework (http://slimframework.com)
 *
 * @license   https://github.com/slimphp/Twig-View/blob/master/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace Slim\Views;

use ArrayAccess;
use ArrayIterator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

/**
 * Twig View
 *
 * This class is a Slim Framework view helper built on top of the Twig templating component.
 * Twig is a PHP component created by Fabien Potencier.
 *
 * @link https://twig.symfony.com/
 */
class Twig implements ArrayAccess
{
    /**
     * Twig loader
     *
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * Twig environment
     *
     * @var Environment
     */
    protected $environment;

    /**
     * Default view variables
     *
     * @var array<string, mixed>
     */
    protected $defaultVariables = [];

    /**
     * @param ServerRequestInterface $request
     * @param string                 $attributeName
     *
     * @return Twig
     */
    public static function fromRequest(ServerRequestInterface $request, string $attributeName = 'view'): self
    {
        $twig = $request->getAttribute($attributeName);
        if ($twig === null || !($twig instanceof self)) {
            throw new RuntimeException(
                'Twig could not be found in the server request attributes using the key "'. $attributeName .'".'
            );
        }

        return $twig;
    }

    /**
     * @param string|string[]      $path     Path(s) to templates directory
     * @param array<string, mixed> $settings Twig environment settings
     *
     * @throws LoaderError When the template cannot be found
     *
     * @return Twig
     */
    public static function create($path, array $settings = []): self
    {
        $loader = new FilesystemLoader();

        $paths = is_array($path) ? $path : [$path];
        foreach ($paths as $namespace => $path) {
            if (is_string($namespace)) {
                $loader->setPaths($path, $namespace);
            } else {
                $loader->addPath($path);
            }
        }

        return new self($loader, $settings);
    }

    /**
     * @param LoaderInterface      $loader   Twig loader
     * @param array<string, mixed> $settings Twig environment settings
     */
    public function __construct(LoaderInterface $loader, array $settings = [])
    {
        $this->loader = $loader;
        $this->environment = new Environment($this->loader, $settings);
    }

    /**
     * Proxy method to add an extension to the Twig environment
     *
     * @param ExtensionInterface $extension A single extension instance or an array of instances
     */
    public function addExtension(ExtensionInterface $extension): void
    {
        $this->environment->addExtension($extension);
    }

    /**
     * Proxy method to add a runtime loader to the Twig environment
     *
     * @param RuntimeLoaderInterface $runtimeLoader
     */
    public function addRuntimeLoader(RuntimeLoaderInterface $runtimeLoader): void
    {
        $this->environment->addRuntimeLoader($runtimeLoader);
    }

    /**
     * Fetch rendered template
     *
     * @param  string               $template Template pathname relative to templates directory
     * @param  array<string, mixed> $data     Associative array of template variables
     *
     * @throws LoaderError  When the template cannot be found
     * @throws SyntaxError  When an error occurred during compilation
     * @throws RuntimeError When an error occurred during rendering
     *
     * @return string
     */
    public function fetch(string $template, array $data = []): string
    {
        $data = array_merge($this->defaultVariables, $data);

        return $this->environment->render($template, $data);
    }

    /**
     * Fetch rendered block
     *
     * @param  string               $template Template pathname relative to templates directory
     * @param  string               $block    Name of the block within the template
     * @param  array<string, mixed> $data     Associative array of template variables
     *
     * @throws Throwable   When an error occurred during rendering
     * @throws LoaderError When the template cannot be found
     * @throws SyntaxError When an error occurred during compilation
     *
     * @return string
     */
    public function fetchBlock(string $template, string $block, array $data = []): string
    {
        $data = array_merge($this->defaultVariables, $data);

        return $this->environment->resolveTemplate($template)->renderBlock($block, $data);
    }

    /**
     * Fetch rendered string
     *
     * @param  string               $string String
     * @param  array<string, mixed> $data   Associative array of template variables
     *
     * @throws LoaderError When the template cannot be found
     * @throws SyntaxError When an error occurred during compilation
     *
     * @return string
     */
    public function fetchFromString(string $string = '', array $data = []): string
    {
        $data = array_merge($this->defaultVariables, $data);

        return $this->environment->createTemplate($string)->render($data);
    }

    /**
     * Output rendered template
     *
     * @param  ResponseInterface    $response
     * @param  string               $template Template pathname relative to templates directory
     * @param  array<string, mixed> $data Associative array of template variables
     *
     * @throws LoaderError  When the template cannot be found
     * @throws SyntaxError  When an error occurred during compilation
     * @throws RuntimeError When an error occurred during rendering
     *
     * @return ResponseInterface
     */
    public function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $response->getBody()->write($this->fetch($template, $data));

        return $response;
    }

    /**
     * Return Twig loader
     *
     * @return LoaderInterface
     */
    public function getLoader(): LoaderInterface
    {
        return $this->loader;
    }

    /**
     * Return Twig environment
     *
     * @return Environment
     */
    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    /**
     * Does this collection have a given key?
     *
     * @param  string $key The data key
     *
     * @return bool
     */
    public function offsetExists($key): bool
    {
        return array_key_exists($key, $this->defaultVariables);
    }

    /**
     * Get collection item for key
     *
     * @param string $key The data key
     *
     * @return mixed The key's value, or the default value
     */
    public function offsetGet($key)
    {
        if (!$this->offsetExists($key)) {
            return null;
        }
        return $this->defaultVariables[$key];
    }

    /**
     * Set collection item
     *
     * @param string $key   The data key
     * @param mixed  $value The data value
     */
    public function offsetSet($key, $value): void
    {
        $this->defaultVariables[$key] = $value;
    }

    /**
     * Remove item from collection
     *
     * @param string $key The data key
     */
    public function offsetUnset($key): void
    {
        unset($this->defaultVariables[$key]);
    }

    /**
     * Get number of items in collection
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->defaultVariables);
    }

    /**
     * Get collection iterator
     *
     * @return ArrayIterator<string, mixed>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->defaultVariables);
    }
}
