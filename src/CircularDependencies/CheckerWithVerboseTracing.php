<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\CircularDependencies;

use Medas\ObjectInstantiator\ParameterResolving;

class CheckerWithVerboseTracing
{
    private const array FUNCTIONS_TO_SKIP = [
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
            if (isset($trace['class'])
                    && in_array($trace['function'], self::FUNCTIONS_TO_SKIP[$trace['class']] ?? [], true)) {
                continue;
            }

            if (!isset($trace['class']) && $trace['function'] === 'service') {
                continue;
            }

            if (isset($trace['class'])) {
                if ($trace['class'] === ParameterResolving\ParameterResolveManager::class
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
            }
            else {
                $source = 'body of ' . $trace['function'];
            }

            foreach ($this->frames as $frame) {
                if ($frame->type === $type && $frame->completed === false) {
                    throw new TracedCircularDependencyFound($this->frames, $type, $source);
                }
            }

            $this->frames[] = new VerboseFrame($type, $source);

            return;
        }
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
