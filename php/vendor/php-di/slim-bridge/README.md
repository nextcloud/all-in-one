# PHP-DI integration with Slim

This package configures Slim to work with the [PHP-DI container](http://php-di.org/).

[![Build Status](https://travis-ci.org/PHP-DI/Slim-Bridge.svg?branch=master)](https://travis-ci.org/PHP-DI/Slim-Bridge)
[![](https://img.shields.io/packagist/dt/php-di/slim-bridge.svg)](https://packagist.org/packages/php-di/slim-bridge)

The full documentation is here: **http://php-di.org/doc/frameworks/slim.html**

## Why?

### PHP-DI as a container

The most obvious difference with the default Slim installation is that you will be using PHP-DI as the container, which has the following benefits:

- [autowiring](http://php-di.org/doc/autowiring.html)
- powerful [configuration format](http://php-di.org/doc/php-definitions.html)
- support for [modular systems](http://php-di.org/doc/definition-overriding.html)
- ...

If you want to learn more about all that PHP-DI can offer [have a look at its introduction](http://php-di.org/).

### Controllers as services

While your controllers can be simple closures, you can also **write them as classes and have PHP-DI instantiate them only when they are called**:

```php
class UserController
{
    private $userRepository;
    
    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function delete($request, $response)
    {
        $this->userRepository->remove($request->getAttribute('id'));
        
        $response->getBody()->write('User deleted');
        return $response;
    }
}

$app->delete('/user/{id}', ['UserController', 'delete']);
```

Dependencies can then be injected in your controller using [autowiring, PHP-DI config files or even annotations](http://php-di.org/doc/definition.html).

### Controller parameters

By default, Slim controllers have a strict signature: `$request, $response, $args`. The PHP-DI bridge offers a more flexible and developer friendly alternative.

Controller parameters can be any of these things:

- the request or response (parameters must be named `$request` or `$response`)
- route placeholders
- request attributes
- services (injected by type-hint)

You can mix all these types of parameters together too. They will be matched by priority in the order of the list above.

#### Request or response injection

You can inject the request or response in the controller parameters by name:

```php
$app->get('/', function (ResponseInterface $response, ServerRequestInterface $request) {
    // ...
});
```

As you can see, the order of the parameters doesn't matter. That allows to skip injecting the `$request` if it's not needed for example.

#### Route placeholder injection

```php
$app->get('/hello/{name}', function ($name, ResponseInterface $response) {
    $response->getBody()->write('Hello ' . $name);
    return $response;
});
```

As you can see above, the route's URL contains a `name` placeholder. By simply adding a parameter **with the same name** to the controller, PHP-DI will directly inject it.

#### Request attribute injection

```php
$app->add(function (ServerRequestInterface $request, RequestHandlerInterface $handler) {
    $request = $request->withAttribute('name', 'Bob');
    $response = $handler->handle($request);
    return $response;
});

$app->get('/', function ($name, ResponseInterface $response) {
    $response->getBody()->write('Hello ' . $name);
    return $response;
});
```

As you can see above, a middleware sets a `name` attribute. By simply adding a parameter **with the same name** to the controller, PHP-DI will directly inject it.

#### Service injection

To inject services into your controllers, you can write them as classes. But if you want to write a micro-application using closures, you don't have to give up dependency injection either.

You can inject services by type-hinting them:

```php
$app->get('/', function (ResponseInterface $response, Twig $twig) {
    return $twig->render($response, 'home.twig');
});
```

> Note: you can only inject services that you can type-hint and that PHP-DI can provide. Type-hint injection is simple, it simply injects the result of `$container->get(/* the type-hinted class */)`.

## Documentation

The documentation can be read here: **http://php-di.org/doc/frameworks/slim.html**
