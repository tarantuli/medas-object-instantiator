<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiatorTest\MockUps;

use Medas\Core\{AsSingleton, BasePackage};

class MockUpPackage extends BasePackage
{
    use AsSingleton;

    public function isTestPackage(): bool
    {
        return true;
    }

    public function dependencies(): array
    {
        return [];
    }

    public function sourceDirectory(): string
    {
        return __DIR__;
    }
}
