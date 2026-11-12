<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator;

use Medas\Core\{AsSingleton, BasePackage, Interfaces\ServiceConfigBuilder};

/**
 * The package of the default object instantiator. It registers the parameter resolvers that do dependency injection:
 * `ServiceFinderByType`, `EnvValueResolver` and `PreferredDefaultFinder`.
 *
 * Registering the package doesn't make its `ObjectInstantiator` the one that's used. The application chooses that
 * with the class it passes to `ServiceConfigBuilder`.
 *
 * @sideEffect `initialize()` adds the three parameter resolvers to the service configuration. They're ordered by their
 *             priorities, not by the order in which they're added.
 */
class ObjectInstantiatorPackage extends BasePackage
{
    use AsSingleton;

    public function dependencies(): array
    {
        return [];
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }

    public function initialize(ServiceConfigBuilder $config): void
    {
        parent::initialize($config);

        $config->addParameterResolver(ArgumentResolving\ServiceFinderByType::class);
        $config->addParameterResolver(ArgumentResolving\EnvValueResolver::class);
        $config->addParameterResolver(ArgumentResolving\PreferredDefaultFinder::class);
    }
}
