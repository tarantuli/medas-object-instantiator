<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\CircularDependencies;

/**
 * A class that's being instantiated, as recorded by `CheckerWithVerboseTracing`.
 */
readonly class VerboseFrame
{
    public function __construct(
        public string $type,

        /** Where the class was requested, like `parameter $logger of method Foo::__construct()`. */
        public string $source,
    )
    {
    }
}
