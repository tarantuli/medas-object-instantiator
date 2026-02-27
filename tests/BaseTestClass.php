<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest;

use Medas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;

abstract class BaseTestClass extends TestCase
{
    protected function loadMockUps(): ServiceManager
    {
        $manager = medas()->serviceManager();

        $manager->config()->addPackage(MockUps\MockUpPackage::instance(), true);

        return $manager;
    }
}
