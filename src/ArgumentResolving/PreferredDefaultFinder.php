<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

use Medas\Core\{
    Attributes\PreferredDefault,
    Attributes\Service,
    Exceptions\ServiceNotFoundByType,
    HandlesByCompiling,
    Interfaces\CompilableParameterResolver,
    ParameterResolverResult
};

#[Service]
class PreferredDefaultFinder implements CompilableParameterResolver
{
    use HandlesByCompiling;

    public function priority(): int
    {
        return -190;
    }

    public function compile(\ReflectionParameter|\ReflectionProperty $parameter): PreferredDefault|null
    {
        return attribute(PreferredDefault::class, $parameter);
    }

    /**
     * @param PreferredDefault $compiled
     */
    public function resolveCompiled(object $compiled): ParameterResolverResult
    {
        try {
            $service = service($compiled->className);
        }
        catch (ServiceNotFoundByType) {
            return new ParameterResolverResult(false);
        }

        return new ParameterResolverResult(true, $service);
    }
}
