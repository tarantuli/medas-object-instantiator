<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

use Medas\Core\{
    CompiledValue,
    Exceptions\MultipleImplementorsFound,
    Exceptions\MultipleImplementorsFoundForParameter,
    HandlesByCompiling,
    Interfaces\CompilableParameterResolver,
    ParameterResolverResult
};

readonly class ServiceFinderByType implements CompilableParameterResolver
{
    use HandlesByCompiling;

    public function priority(): int
    {
        return -200;
    }

    /**
     * Compiles to the type to resolve. When that type has multiple implementors, it compiles to an
     * @class(AmbiguousServiceParameter) instead, which carries the details of the parameter for the exception. The
     * service mapping is fixed once the configuration is built, so ambiguity is known at this point.
     */
    public function compile(
        \ReflectionParameter|\ReflectionProperty $parameter
    ): CompiledValue|AmbiguousServiceParameter|null
    {
        foreach (normalizeType($parameter->getType()) as $type) {
            $typeName = $type->getName();

            try {
                if (null !== sm()->findImplementingClass($typeName)) {
                    return new CompiledValue($typeName);
                }
            }
            catch (MultipleImplementorsFound $exception) {
                return new AmbiguousServiceParameter(
                    $exception->type,
                    $parameter->name,

                    // Parameters of closures and functions have no declaring class
                    $parameter->getDeclaringClass()?->name ?? '',
                    $parameter instanceof \ReflectionParameter
                        ? $parameter->getDeclaringFunction()->name
                        : '',
                    $exception->implementors,
                );
            }
        }

        return null;
    }

    /**
     * @param CompiledValue|AmbiguousServiceParameter $compiled
     */
    public function resolveCompiled(object $compiled): ParameterResolverResult
    {
        if ($compiled instanceof AmbiguousServiceParameter) {
            throw new MultipleImplementorsFoundForParameter(
                $compiled->type,
                $compiled->parameterName,
                $compiled->className,
                $compiled->methodName,
                $compiled->implementors,
                new MultipleImplementorsFound($compiled->type, $compiled->implementors),
            );
        }

        return new ParameterResolverResult(true, sm()->resolve($compiled->value));
    }
}
