<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

use Medas\Core\{
    Attributes\PreferredDefault,
    Attributes\Service,
    Exceptions\ServiceNotFoundByType,
    Interfaces\ParameterResolver,
    ParameterResolverResult
};

#[Service]
class PreferredDefaultFinder implements ParameterResolver
{
    public function priority(): int
    {
        return -190;
    }

    public function handle(\ReflectionParameter|\ReflectionProperty $parameter): ParameterResolverResult
    {
        $preferredDefault = attribute(PreferredDefault::class, $parameter);

        if (!$preferredDefault) {
            return new ParameterResolverResult(false);
        }

        try {
            $service = service($preferredDefault->className);
        }
        catch (ServiceNotFoundByType) {
            return new ParameterResolverResult(false);
        }

        return new ParameterResolverResult(true, $service);
    }
}
