<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

use Medas\Core\{
    Attributes\EnvValue,
    Attributes\Service,
    HandlesByCompiling,
    Interfaces\CompilableParameterResolver,
    ParameterResolverResult
};
use Medas\ObjectInstantiator\Exceptions\EnvValueDoesNotExist;

#[Service]
class EnvValueResolver implements CompilableParameterResolver
{
    use HandlesByCompiling;

    public function priority(): int
    {
        return -180;
    }

    public function compile(\ReflectionParameter|\ReflectionProperty $parameter): EnvValue|null
    {
        return attribute(EnvValue::class, $parameter);
    }

    /**
     * @param EnvValue $compiled
     */
    public function resolveCompiled(object $compiled): ParameterResolverResult
    {
        if (!array_key_exists($compiled->name, $_ENV)) {
            throw new EnvValueDoesNotExist($compiled->name);
        }

        return new ParameterResolverResult(true, $_ENV[$compiled->name]);
    }
}
