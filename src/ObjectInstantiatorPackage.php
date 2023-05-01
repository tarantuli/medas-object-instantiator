<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator;

use Medas\Core\AsSingleton;
use Medas\ServiceManager\BasePackage;

class ObjectInstantiatorPackage extends BasePackage
{
    use AsSingleton;

    public function dependencies(): array
    {
        return $this->dependenciesByClass([
        ]);
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }
}
