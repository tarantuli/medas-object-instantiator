<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest\MockUps\CircularDependencies;

use Medas\Core\Attributes\Service;

#[Service]
class DirectDependency1
{
    public function __construct(
        private DirectDependency2 $directDependency2,
    )
    {
    }
}
