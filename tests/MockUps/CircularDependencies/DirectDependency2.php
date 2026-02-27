<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest\MockUps\CircularDependencies;

use Medas\Core\Attributes\Service;

#[Service]
class DirectDependency2
{
    public function __construct(
        private DirectDependency1 $directDependency1,
    )
    {
    }
}
