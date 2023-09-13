<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ParameterResolving;

use Medas\Core\{Attributes\Service, Interfaces\ParameterResolver, ParameterResolverResult};
use Medas\Core\Attributes\PreferredDefault;

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

        return new ParameterResolverResult(true, service($preferredDefault->className));
    }
}
