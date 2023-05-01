<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator;

use Medas\Core\AsSingleton;
use Medas\ObjectInstantiator\ParameterResolving\PreferredDefaultFinder;
use Medas\ServiceManager\BasePackage;
use Medas\ServiceManager\ServiceConfig;

class ObjectInstantiatorPackage extends BasePackage
{
    use AsSingleton;

    public function dependencies(): array
    {
        return $this->dependenciesByClass([
        ]);
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }

    public function initialize(ServiceConfig $config): void
    {
        parent::initialize($config);

        $config->addParameterResolver(
            service(PreferredDefaultFinder::class)
        );
    }
}
