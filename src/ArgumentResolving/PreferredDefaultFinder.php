<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

use Medas\Core\{
    Attributes\PreferredDefault,
    Exceptions\MultipleImplementorsFound,
    HandlesByCompiling,
    Interfaces\CompilableParameterResolver,
    Interfaces\ResolvesLazily,
    ParameterResolverResult
};

/**
 * Resolves parameters and properties marked with `#[PreferredDefault]` to the service of the class named in the
 * attribute. Use it to pick one implementation for a parameter whose type has several.
 *
 * When no service provides that class, the parameter is left to the next resolver, normally `ServiceFinderByType`.
 * Like that resolver, it injects services with `resolveLazy()` when the service manager implements `ResolvesLazily`,
 * so a service that hasn't been instantiated yet arrives as a lazy object.
 *
 * @assumes The class in the attribute fits the parameter's type. That isn't checked here; a mismatch ends in a
 *          `TypeError` when the arguments are passed.
 * @assumes The service mapping is complete when a parameter is compiled, which is the case once the service
 *          configuration has been built.
 * @sideEffect The first time a service is injected, it's registered with the service manager, as a lazy object or a
 *             new instance.
 * @cache The cached plan keeps the attribute, and only when a service provides its class. Changes to the service
 *        mapping only take effect once the cache is cleared.
 * @see \Medas\Core\Attributes\PreferredDefault
 */
class PreferredDefaultFinder implements CompilableParameterResolver
{
    use HandlesByCompiling;

    /**
     * Before `ServiceFinderByType` (`-200`), so the attribute wins from the lookup by type.
     */
    public function priority(): int
    {
        return -190;
    }

    /**
     * Compiles to the attribute, or to `null` when the parameter has no attribute or no service provides its class.
     * The service mapping is fixed once the configuration is built, so that is known at this point.
     *
     * @throws MultipleImplementorsFound When the class in the attribute is itself provided by several services, and
     *                                   no type binding decides between them.
     */
    public function compile(\ReflectionParameter|\ReflectionProperty $parameter): PreferredDefault|null
    {
        $preferredDefault = attribute(PreferredDefault::class, $parameter);

        if ($preferredDefault === null || sm()->findImplementingClass($preferredDefault->className) === null) {
            return null;
        }

        return $preferredDefault;
    }

    /**
     * @param PreferredDefault $compiled
     */
    public function resolveCompiled(object $compiled): ParameterResolverResult
    {
        $serviceManager = sm();

        $service = $serviceManager instanceof ResolvesLazily
            ? $serviceManager->resolveLazy($compiled->className)
            : $serviceManager->resolve($compiled->className);

        return new ParameterResolverResult(true, $service);
    }
}
