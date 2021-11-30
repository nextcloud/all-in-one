<?php

namespace AIO;

use AIO\Docker\DockerHubManager;
use DI\Container;

class DependencyInjection
{
    public static function GetContainer() : Container {
        $container = new Container();

        $container->set(
            DockerHubManager::class,
            new DockerHubManager()
        );

        $container->set(
            \AIO\Data\ConfigurationManager::class,
            new \AIO\Data\ConfigurationManager()
        );
        $container->set(
            \AIO\Docker\DockerActionManager::class,
            new \AIO\Docker\DockerActionManager(
                $container->get(\AIO\Data\ConfigurationManager::class),
                $container->get(\AIO\ContainerDefinitionFetcher::class),
                $container->get(DockerHubManager::class)
            )
        );
        $container->set(
            \AIO\Auth\PasswordGenerator::class,
            new \AIO\Auth\PasswordGenerator()
        );
        $container->set(
            \AIO\Auth\AuthManager::class,
            new \AIO\Auth\AuthManager($container->get(\AIO\Data\ConfigurationManager::class))
        );
        $container->set(
            \AIO\Data\Setup::class,
            new \AIO\Data\Setup(
                $container->get(\AIO\Auth\PasswordGenerator::class),
                $container->get(\AIO\Data\ConfigurationManager::class)
            )
        );

        return $container;
    }
}