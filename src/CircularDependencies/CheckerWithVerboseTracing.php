<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\CircularDependencies;

use Medas\ObjectInstantiator\ArgumentResolving;

class CheckerWithVerboseTracing
{
    private const array METHODS_TO_SKIP = [
        'Medas\ObjectInstantiator\ObjectInstantiator' => [
            'instantiate',
            'getConstructorArgumentValues',
        ],
        'Medas\ObjectInstantiator\ParameterResolving\ServiceFinderByType' => [
            'handle',
        ],
        'Medas\ObjectInstantiator\CircularDependencies\CheckerWithVerboseTracing' => [
            'check',
        ],
        'Medas\ServiceManager\ServiceManager' => [
            'resolve',
        ],
    ];

    /** @var VerboseFrame[] */
    private array $frames = [];

    public function check(string $type): void
    {
        foreach (debug_backtrace() as $trace) {
            if (!array_key_exists('class', $trace)) {
                // Skip all global functions, closures, etc.
                continue;
            }

            if (array_key_exists($trace['class'], self::METHODS_TO_SKIP)
                    && in_array($trace['function'], self::METHODS_TO_SKIP[$trace['class']], true)) {
                continue;
            }

            $source = $this->compileSource($trace);

            foreach ($this->frames as $frame) {
                if ($frame->type === $type && $frame->completed === false) {
                    throw new TracedCircularDependencyFound($this->frames, $type, $source);
                }
            }

            $this->frames[] = new VerboseFrame($type, $source);

            return;
        }
    }

    private function compileSource(array $trace): string
    {
        if ($trace['class'] === ArgumentResolving\ArgumentResolver::class
                && $trace['function'] === 'resolveParameter') {
            /** @var \ReflectionParameter $parameter */
            $parameter = $trace['args'][0];

            $source = sprintf(
                "parameter $%s of method %s::%s()",
                $parameter->name,
                $parameter->getDeclaringClass()->name,
                $parameter->getDeclaringFunction()->name
            );
        }
        else {
            $source = 'body of ' . $trace['class'] . $trace['type'] . $trace['function'];
        }

        return $source;
    }

    public function markComplete(string $type): void
    {
        foreach ($this->frames as $value) {
            if ($value->type === $type) {
                $value->completed = true;
            }
        }
    }

    public function reset(): void
    {
        $this->frames = [];
    }
}
