<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\CircularDependencies;

use Medas\Core\Exceptions\CircularDependencyFound;

/**
 * Detects circular dependencies, by keeping track of the classes whose constructor arguments are being resolved.
 *
 * `ObjectInstantiator` calls `check()` before resolving a class's constructor arguments and `markComplete()` after,
 * also when resolving fails.
 *
 * @state Keeps the classes being instantiated, so one instance must be used for the whole process, by one
 *        `ObjectInstantiator`.
 * @notFor Finding out where each class in a cycle was requested; `CheckerWithVerboseTracing` does that, at a cost.
 * @see CheckerWithVerboseTracing
 */
class Checker
{
    /** @var array<string, true> */
    private array $instantiating = [];

    /**
     * Records that `$type` is being instantiated.
     *
     * @throws CircularDependencyFound When `$type` is already being instantiated further up the call stack.
     */
    public function check(string $type): void
    {
        if (array_key_exists($type, $this->instantiating)) {
            throw new CircularDependencyFound(array_keys($this->instantiating), $type);
        }

        $this->instantiating[$type] = true;
    }

    /**
     * Records that `$type` is no longer being instantiated, whether that succeeded or not.
     */
    public function markComplete(string $type): void
    {
        unset($this->instantiating[$type]);
    }
}
