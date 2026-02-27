<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest\MockUps\CircularDependencies;

use Medas\Core\Attributes\Service;

#[Service]
class IndirectDependency3
{
    public function __construct(
        private IndirectDependency1 $indirectDependency1,
    )
    {
    }
}
