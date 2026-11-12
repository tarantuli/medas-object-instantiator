<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest;

use Medas\ServiceManager\ServiceManager;
use PHPUnit\Framework\TestCase;

abstract class BaseTestClass extends TestCase
{
    /**
     * The service manager built in `phpunit.bootstrap.php`, which has the mock-ups registered.
     */
    protected function serviceManager(): ServiceManager
    {
        return medas()->serviceManager();
    }
}
