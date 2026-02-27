<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest\Functional;

use Medas\ObjectInstantiator\Exceptions\CircularDependencyFound;
use Medas\ObjectInstantiatorTest\BaseTestClass;
use Medas\ObjectInstantiatorTest\MockUps\CircularDependencies\{
    DirectDependency1,
    IndirectDependency1,
    SelfDependency
};

class CircularDependenciesTest extends BaseTestClass
{
    public function testSelfDependency(): void
    {
        $manager = $this->loadMockUps();

        self::expectException(CircularDependencyFound::class);

        $manager->resolve(SelfDependency::class);
    }

    public function testDirectDependency(): void
    {
        $manager = $this->loadMockUps();

        self::expectException(CircularDependencyFound::class);

        $manager->resolve(DirectDependency1::class);
    }

    public function testIndirectDependency(): void
    {
        $manager = $this->loadMockUps();

        self::expectException(CircularDependencyFound::class);

        $manager->resolve(IndirectDependency1::class);
    }
}
