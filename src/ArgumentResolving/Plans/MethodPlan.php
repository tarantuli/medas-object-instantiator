<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving\Plans;

/**
 * The pre-analysed resolution of all parameters of a method, so resolving its arguments needs no reflection.
 */
readonly class MethodPlan
{
    /**
     * @param ParameterPlan[] $parameters
     * @param array<string, int> $sourceFiles The files the plan was compiled from, with their modification times, so
     *                                        development mode can detect that a plan is stale. Empty outside of
     *                                        development mode.
     * @param bool $canBeLazy For constructors: whether the class can be instantiated as a lazy object
     */
    public function __construct(
        public string $className,
        public string $methodName,
        public array  $parameters,
        public array  $sourceFiles,
        public bool   $canBeLazy = false,
    )
    {
    }

    /**
     * A positional list keeps the cached plans small.
     */
    public function __serialize(): array
    {
        return [
            $this->className,
            $this->methodName,
            $this->parameters,
            $this->sourceFiles,
            $this->canBeLazy
        ];
    }

    public function __unserialize(array $data): void
    {
        [
            $this->className,
            $this->methodName,
            $this->parameters,
            $this->sourceFiles,
            $this->canBeLazy
        ] = $data;
    }

    public function isFresh(): bool
    {
        // is_file() fills PHP's stat cache, so filemtime() does not hit the file system again
        return array_all(
            $this->sourceFiles,
            fn($modifiedAt, $file) => is_file($file) && filemtime($file) === $modifiedAt
        );
    }

    /**
     * For the rare cases that still need reflection: resolvers or processors that are not compilable, default values
     * that must be read each time, and exceptions that describe the parameter.
     */
    public function reflectParameter(ParameterPlan $parameter): \ReflectionParameter
    {
        return new \ReflectionParameter(
            [$this->className, $this->methodName],
            $parameter->position
        );
    }
}
