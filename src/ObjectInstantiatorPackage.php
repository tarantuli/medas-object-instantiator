<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator;

use Medas\Core\AsSingleton;
use Medas\ServiceManager\{BasePackage, ServiceConfig};

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

    public function initialize(ServiceConfig $config): void
    {
        parent::initialize($config);

        $config->addParameterResolver(service(ParameterResolving\PreferredDefaultFinder::class));
    }
}
