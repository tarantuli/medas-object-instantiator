<?php

declare(strict_types=1);

use Medas\ObjectInstantiator\ObjectInstantiatorPackage;
use Medas\ServiceManager\{ServiceConfig, ServiceManager};

chdir(__DIR__);

new ServiceManager(function (): ServiceConfig {
    $config = new ServiceConfig();

    $config->addPackages([
        ObjectInstantiatorPackage::instance(),
    ]);

    return $config;
});
