<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

use Medas\Core\{
    CompiledValue,
    Exceptions\MultipleImplementorsFound,
    Exceptions\MultipleImplementorsFoundForParameter,
    HandlesByCompiling,
    Interfaces\CompilableParameterResolver,
    Interfaces\ResolvesLazily,
    ParameterResolverResult
};

/**
 * Resolves parameters and properties whose type is provided by a service, by injecting that service. This is the core
 * of dependency injection in medas.
 *
 * When the service manager implements `ResolvesLazily`, services are injected with `resolveLazy()`, so a service that
 * hasn't been instantiated yet arrives as a lazy object (see @class(Medas\Core\Attributes\Service)).
 *
 * @assumes The service mapping is complete when a parameter is compiled, which is the case once the service
 *          configuration has been built.
 * @sideEffect The first time a service is injected, it's registered with the service manager, as a lazy object or a
 *             new instance.
 * @cache The cached plan keeps the type to resolve, or the fact that it's ambiguous. Changes to the service mapping,
 *        like a new implementation of an interface, only take effect once the cache is cleared.
 * @notFor Choosing between several implementations of a type. Use `#[PreferredDefault]` or a type binding for that.
 * @see PreferredDefaultFinder
 */
readonly class ServiceFinderByType implements CompilableParameterResolver
{
    use HandlesByCompiling;

    /**
     * The lowest of this package's resolvers, so resolvers driven by attributes get a chance first.
     */
    public function priority(): int
    {
        return -200;
    }

    /**
     * Compiles to the type to resolve. When that type has multiple implementors, it compiles to an
     * @class(AmbiguousServiceParameter) instead, which carries the details of the parameter for the exception. The
     * service mapping is fixed once the configuration is built, so ambiguity is known at this point.
     *
     * For a union type, the types are tried in the order reflection reports them. The first one a service provides is
     * used, and the first ambiguous one ends the search.
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
     *
     * @throws MultipleImplementorsFoundForParameter When the parameter's type is provided by several services, and no
     *                                               type binding decides between them.
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

        $serviceManager = sm();

        // Dependencies are injected as lazy objects where possible, so services that are injected but never used
        // (and their own dependencies) are never instantiated
        $service = $serviceManager instanceof ResolvesLazily
            ? $serviceManager->resolveLazy($compiled->value)
            : $serviceManager->resolve($compiled->value);

        return new ParameterResolverResult(true, $service);
    }
}
