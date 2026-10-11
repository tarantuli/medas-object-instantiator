<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

/**
 * The compiled form of a parameter whose type has multiple implementing services, so resolving it reports the
 * parameter in the exception.
 */
readonly class AmbiguousServiceParameter
{
    /**
     * @param string[] $implementors
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
