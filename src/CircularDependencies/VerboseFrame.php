<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\CircularDependencies;

readonly class VerboseFrame
{
    public function __construct(
        public string $type,
        public string $source,
    )
    {
    }
}
