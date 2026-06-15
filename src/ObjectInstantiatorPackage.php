<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator;

use Medas\Core\{AsSingleton, BasePackage, Interfaces\ServiceConfigBuilder};

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

        $config->addParameterResolver(ParameterResolving\ServiceFinderByType::class);
        $config->addParameterResolver(ParameterResolving\EnvValueResolver::class);
        $config->addParameterResolver(ParameterResolving\EnvValueResolver::class);
    }
}
