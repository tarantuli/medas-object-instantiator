<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest\MockUps\CircularDependencies;

use Medas\Core\Attributes\Service;

// Not lazy, because a lazy dependency would break the cycle that this mock-up is meant to cause
#[Service(lazy: false)]
class IndirectDependency1
{
    public function __construct(
        private IndirectDependency2 $indirectDependency2,
    )
    {
    }
}
