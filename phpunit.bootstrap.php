<?php

declare(strict_types=1);

use Medas\ObjectInstantiator\{ObjectInstantiator, ObjectInstantiatorPackage};
use Medas\ObjectInstantiatorTest\MockUps\MockUpPackage;
use Medas\ServiceManager\{ServiceConfigBuilder, ServiceManager};

chdir(__DIR__);

require_once __DIR__ . '/vendor/autoload.php';

// The configuration is built once, so the mock-ups must be registered here rather than in the tests. Without a cache,
// it's built again in every test run.
new ServiceManager(function (): ServiceConfigBuilder {
    $config = new ServiceConfigBuilder(ObjectInstantiator::class);

    $config->addPackages([
        ObjectInstantiatorPackage::instance(),
        MockUpPackage::instance(),
    ]);

    return $config;
});
