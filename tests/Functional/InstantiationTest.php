<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest\Functional;

use Medas\ObjectInstantiator\{Exceptions\CouldNotResolveParameter, ObjectInstantiator};
use Medas\ObjectInstantiatorTest\BaseTestClass;
use Medas\ObjectInstantiatorTest\MockUps\Instantiation\{ClassWithArgument, Logger};

class InstantiationTest extends BaseTestClass
{
    public function testBasicInstantiation(): void
    {
        $service = medas()->objectInstantiator()->instantiate(Logger::class);

        self::assertInstanceOf(Logger::class, $service);
    }

    public function testNonServiceArgumentFail(): void
    {
        $this->loadMockUps();
        $this->expectException(CouldNotResolveParameter::class);

        medas()->objectInstantiator()->instantiate(ClassWithArgument::class);
    }

    public function testPassArgumentSuccess(): void
    {
        $this->loadMockUps();

        service(ObjectInstantiator::class)->resetInstantiatingStack();

        $object = medas()->objectInstantiator()->instantiate(
            ClassWithArgument::class,
            ['number' => 10]
        );

        self::assertInstanceOf(ClassWithArgument::class, $object);
    }
}
