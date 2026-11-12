<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\CircularDependencies;

use Medas\ObjectInstantiator\ArgumentResolving;

class CheckerWithVerboseTracing
{
    /*
     * Every method between check() and the code that asked for the type must be listed here, otherwise that method is
     * reported as the source instead.
     */
    private const array METHODS_TO_SKIP = [
        'Medas\ObjectInstantiator\ObjectInstantiator' => [
            'instantiate',
            'instantiateLazy',
            'construct',
            'initializeGhost',
            'resolveConstructorArguments',
        ],
        'Medas\ObjectInstantiator\ArgumentResolving\ServiceFinderByType' => [
            'handle',
            'resolveCompiled',
        ],
        'Medas\ObjectInstantiator\ArgumentResolving\PreferredDefaultFinder' => [
            'handle',
            'resolveCompiled',
        ],
        'Medas\ObjectInstantiator\CircularDependencies\CheckerWithVerboseTracing' => [
            'check',
        ],
        'Medas\ServiceManager\ServiceManager' => [
            'resolve',
            'resolveLazy',
            'resolveUnresolved',
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
                if ($frame->type === $type) {
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
            /** @var \ReflectionParameter|\ReflectionProperty $parameter */
            $parameter = $trace['args'][0];

            // Properties have no declaring function, and parameters of closures and functions have no declaring class
            if ($parameter instanceof \ReflectionProperty) {
                $source = sprintf(
                    "property $%s of class %s",
                    $parameter->name,
                    $parameter->getDeclaringClass()->name
                );
            }
            elseif ($parameter->getDeclaringClass() === null) {
                $source = sprintf(
                    "parameter $%s of function %s()",
                    $parameter->name,
                    $parameter->getDeclaringFunction()->name
                );
            }
            else {
                $source = sprintf(
                    "parameter $%s of method %s::%s()",
                    $parameter->name,
                    $parameter->getDeclaringClass()->name,
                    $parameter->getDeclaringFunction()->name
                );
            }
        }
        elseif (
            $trace['class'] === ArgumentResolving\ArgumentResolver::class
            && $trace['function'] === 'resolvePlannedParameter'
        ) {
            /** @var ArgumentResolving\Plans\MethodPlan $plan */
            /** @var ArgumentResolving\Plans\ParameterPlan $parameter */
            [$plan, $parameter] = $trace['args'];

            $source = sprintf(
                "parameter $%s of method %s::%s()",
                $parameter->name,
                $plan->className,
                $plan->methodName
            );
        }
        else {
            $source = 'body of ' . $trace['class'] . $trace['type'] . $trace['function'];
        }

        return $source;
    }

    public function markComplete(string $type): void
    {
        // A type has at most one frame, because a second check() for it throws
        $this->frames = array_values(array_filter($this->frames, fn(VerboseFrame $frame) => $frame->type !== $type));
    }
}
