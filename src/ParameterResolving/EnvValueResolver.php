<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ParameterResolving;

use Medas\Core\{
    Attributes\EnvValue,
    Attributes\Service,
    Interfaces\ParameterResolver,
    ParameterResolverResult
};
use Medas\ObjectInstantiator\Exceptions\EnvValueDoesNotExist;

#[Service]
class EnvValueResolver implements ParameterResolver
{
    public function priority(): int
    {
        return -180;
    }

    public function handle(\ReflectionParameter|\ReflectionProperty $parameter): ParameterResolverResult
    {
        $envValue = attribute(EnvValue::class, $parameter);

        if (!$envValue) {
            return new ParameterResolverResult(false);
        }

        if (!array_key_exists($envValue->name, $_ENV)) {
            throw new EnvValueDoesNotExist($envValue->name);
        }

        return new ParameterResolverResult(true, $_ENV[$envValue->name]);
    }
}
