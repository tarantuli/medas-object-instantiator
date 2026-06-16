<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\CircularDependencies;

use Medas\Core\Exceptions\CircularDependencyFound;

class Checker
{
    /** @var string[] */
    private array $instantiating = [];

    public function check(string $type): void
    {
        if (array_key_exists($type, $this->instantiating)) {
            throw new CircularDependencyFound(array_keys($this->instantiating), $type);
        }

        $this->instantiating[$type] = true;
    }

    public function markComplete(string $type): void
    {
        unset($this->instantiating[$type]);
    }

    public function reset(): void
    {
        $this->instantiating = [];
    }
}
