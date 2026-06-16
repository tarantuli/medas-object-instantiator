<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

use Medas\Core\{
    Exceptions\MultipleImplementorsFound,
    Exceptions\MultipleImplementorsFoundForParameter,
    Interfaces\ParameterResolver,
    ParameterResolverResult
};

readonly class ServiceFinderByType implements ParameterResolver
{
    public function priority(): int
    {
        return -200;
    }

    public function handle(\ReflectionParameter|\ReflectionProperty $parameter): ParameterResolverResult
    {
        $service = $this->findServiceImplementingTypes($parameter);

        if (null === $service) {
            return new ParameterResolverResult(false);
        }

        try {
            $result = sm()->resolve($service);
        }
        catch (MultipleImplementorsFound $exception) {
            throw new MultipleImplementorsFoundForParameter(
                $exception->type,
                $parameter->name,
                $parameter->getDeclaringClass()->name,
                $parameter->getDeclaringFunction()->name,
                $exception->implementors,
                $exception
            );
        }

        return new ParameterResolverResult(true, $result);
    }

    private function findServiceImplementingTypes(\ReflectionParameter|\ReflectionProperty $parameter): string|null
    {
        $types = parameterTypes($parameter);

        foreach ($types as $type) {
            $typeName = $type->getName();

            if (null !== sm()->findImplementingClass($typeName)) {
                return $typeName;
            }
        }

        return null;
    }
}
