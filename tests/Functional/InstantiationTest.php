<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest\Functional;

use Medas\Core\GlobalRepository;
use Medas\ObjectInstantiator\Exceptions\CouldNotResolveParameter;
use Medas\ObjectInstantiator\ObjectInstantiator;
use Medas\ObjectInstantiatorTest\BaseTestClass;
use Medas\ObjectInstantiatorTest\MockUps\Instantiation\ClassWithArgument;
use Medas\ObjectInstantiatorTest\MockUps\Instantiation\Logger;

class InstantiationTest extends BaseTestClass
{
    public function testBasicInstantiation(): void
    {
        $service = GlobalRepository::objectInstantiator()->instantiate(Logger::class);

        self::assertInstanceOf(Logger::class, $service);
    }

    public function testNonServiceArgumentFail(): void
    {
        $this->loadMockUps();
        $this->expectException(CouldNotResolveParameter::class);
        GlobalRepository::objectInstantiator()->instantiate(ClassWithArgument::class);
    }

    public function testPassArgumentSuccess(): void
    {
        $this->loadMockUps();
        service(ObjectInstantiator::class)->resetInstantiatingStack();

        $object = GlobalRepository::objectInstantiator()->instantiate(ClassWithArgument::class, ['number' => 10]);
        self::assertInstanceOf(ClassWithArgument::class, $object);
    }
}
