<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

use Medas\Core\{
    Attributes\EnvValue,
    HandlesByCompiling,
    Interfaces\CompilableParameterResolver,
    ParameterResolverResult
};
use Medas\ObjectInstantiator\Exceptions\EnvValueDoesNotExist;

/**
 * Resolves parameters and properties marked with `#[EnvValue]` to the value of that variable in `$_ENV`.
 *
 * The value is passed on as `$_ENV` holds it, which is normally a string.
 *
 * @assumes The variable is in `$_ENV` when the argument is resolved. PHP only fills `$_ENV` when `variables_order`
 *          contains `E`; otherwise something else, like a `.env` loader, has to fill it.
 * @cache Only the variable's name is kept in the cached plan. The value is read from `$_ENV` on every resolution.
 * @see \Medas\Core\Attributes\EnvValue
 */
class EnvValueResolver implements CompilableParameterResolver
{
    use HandlesByCompiling;

    /**
     * Before `PreferredDefaultFinder` (`-190`) and `ServiceFinderByType` (`-200`).
     */
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
     *
     * @throws EnvValueDoesNotExist When the variable isn't in `$_ENV`, even when the parameter has a default value or
     *                              allows `null`.
     */
    public function resolveCompiled(object $compiled): ParameterResolverResult
    {
        if (!array_key_exists($compiled->name, $_ENV)) {
            throw new EnvValueDoesNotExist($compiled->name);
        }

        return new ParameterResolverResult(true, $_ENV[$compiled->name]);
    }
}
