<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\CircularDependencies;

use Medas\ObjectInstantiator\ArgumentResolving;

/**
 * Detects circular dependencies like @class(Medas\ObjectInstantiator\CircularDependencies\Checker), but also
 * remembers where each class was requested, so the exception describes the whole cycle. `ObjectInstantiator` uses it
 * instead of `Checker` when `TRACE_CIRCULAR_DEPENDENCIES` is defined.
 *
 * Where a class was requested is the first frame in the call stack that isn't part of the instantiation itself: the
 * parameter that needed it, or else the method that asked for it. When there's no such frame, it's `(top level)`.
 *
 * @assumes `METHODS_TO_SKIP` lists every method of the object instantiator and the service manager that sits between
 *          `check()` and the code that asked for the type. A method that's missing is reported as the source.
 * @state Keeps a frame for each class being instantiated, so one instance must be used for the whole process.
 * @performance `check()` takes a backtrace, with arguments, on every instantiation, so it's meant for debugging only.
 */
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
            'findSource',
        ],
        'Medas\ServiceManager\ServiceManager' => [
            'resolve',
            'resolveLazy',
            'resolveUnresolved',
        ],
    ];

    /** @var VerboseFrame[] */
    private array $frames = [];

    /**
     * Records that `$type` is being instantiated, and where it was requested.
     *
     * @throws TracedCircularDependencyFound When `$type` is already being instantiated further up the call stack.
     */
    public function check(string $type): void
    {
        $source = $this->findSource();

        foreach ($this->frames as $frame) {
            if ($frame->type === $type) {
                throw new TracedCircularDependencyFound($this->frames, $type, $source);
            }
        }

        $this->frames[] = new VerboseFrame($type, $source);
    }

    private function findSource(): string
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

            return $this->compileSource($trace);
        }

        // Only functions and skipped methods are on the call stack, like when a script calls service()
        return '(top level)';
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

    /**
     * Forgets the frame of `$type`, whether instantiating it succeeded or not.
     */
    public function markComplete(string $type): void
    {
        // A type has at most one frame, because a second check() for it throws
        $this->frames = array_values(array_filter($this->frames, fn(VerboseFrame $frame) => $frame->type !== $type));
    }
}
