<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest\MockUps\Instantiation;

class ClassWithArgument
{
    public function __construct(
        private readonly Logger $logger,
        private readonly int    $number,
    )
    {
    }
}
