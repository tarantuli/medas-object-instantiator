<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\CircularDependencies;

class VerboseFrame
{
    public bool $completed = false;

    public function __construct(
        public readonly string $type,
        public readonly string $source,
    )
    {
    }
}
