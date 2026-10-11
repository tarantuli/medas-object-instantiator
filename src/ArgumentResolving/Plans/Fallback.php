<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving\Plans;

/**
 * What to do with a parameter when none of the parameter resolvers handled it.
 *
 * Backed by an int to keep cached plans small.
 */
enum Fallback: int
{
    /**
     * Use the default value stored in the plan.
     */
    case DefaultValue = 1;

    /**
     * Read the default value through reflection each time. Used when the default value is a constant (which may only
     * be defined at runtime) or contains objects (`new` in initialisers), which must be a fresh instance every time.
     */
    case DefaultValueFromReflection = 2;
    case null = 3;

    /**
     * Throw @class(Medas\Core\Exceptions\CouldNotResolveParameter).
     */
    case Fail = 4;
}
