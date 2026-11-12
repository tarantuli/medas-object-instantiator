<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest\Functional;

use Medas\Core\Exceptions\CouldNotResolveParameter;
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
        $this->expectException(CouldNotResolveParameter::class);

        medas()->objectInstantiator()->instantiate(ClassWithArgument::class);
    }

    public function testPassArgumentSuccess(): void
    {
        $object = medas()->objectInstantiator()->instantiate(
            ClassWithArgument::class,
            ['number' => 10]
        );

        self::assertInstanceOf(ClassWithArgument::class, $object);
    }
}
