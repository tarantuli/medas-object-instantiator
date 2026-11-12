<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

/**
 * The compiled form of a parameter whose type has multiple implementing services, so resolving it reports the
 * parameter in the exception.
 *
 * @see ServiceFinderByType
 */
readonly class AmbiguousServiceParameter
{
    /**
     * @param string   $className    Empty for parameters of closures and functions
     * @param string   $methodName   Empty for properties
     * @param string[] $implementors The services that provide `$type`
     */
    public function __construct(
        public string $type,
        public string $parameterName,
        public string $className,
        public string $methodName,
        public array  $implementors,
    )
    {
    }
}
