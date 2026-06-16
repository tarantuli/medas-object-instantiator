<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator;

use Medas\Core\{Attributes\Service, Interfaces\ObjectInstantiator as ObjectInstantiatorInterface};

#[Service]
readonly class ObjectInstantiator implements ObjectInstantiatorInterface
{
    private ArgumentResolving\ArgumentResolver $argumentResolver;
    private CircularDependencies\Checker $circularDependencyChecker;
    private CircularDependencies\CheckerWithVerboseTracing $verbodeCircularDependencyChecker;

    public function __construct(
        array $parameterResolverNames,
        array $argumentProcessorNames,
    )
    {
        // This service is *not* instantiated automatically,
        // so don't add arguments and expect them to be injected.
        $this->argumentResolver = new ArgumentResolving\ArgumentResolver(
            $parameterResolverNames,
            $argumentProcessorNames
        );

        $this->circularDependencyChecker = new CircularDependencies\Checker();
        $this->verbodeCircularDependencyChecker = new CircularDependencies\CheckerWithVerboseTracing();
    }

    public function instantiate(string $type, array $givenArguments = []): object
    {
        if (defined('TRACE_CIRCULAR_DEPENDENCIES')) {
            $this->verbodeCircularDependencyChecker->check($type);
        }
        else {
            $this->circularDependencyChecker->check($type);
        }

        try {
            $arguments = $this->getConstructorArgumentValues($type, $givenArguments);
        }

        finally{
            if (defined('TRACE_CIRCULAR_DEPENDENCIES')) {
                $this->verbodeCircularDependencyChecker->markComplete($type);
            }
            else {
                $this->circularDependencyChecker->markComplete($type);
            }
        }

        return new $type(...$arguments);
    }

    private function getConstructorArgumentValues(string $className, array $givenArguments): array
    {
        $class = new \ReflectionClass($className);

        if (!$constructor = $class->getConstructor()) {
            return [];
        }

        return $this->argumentResolver->resolveMethodParameters($constructor, $givenArguments);
    }

    public function resetInstantiatingStack(): void
    {
        if (defined('TRACE_CIRCULAR_DEPENDENCIES')) {
            $this->verbodeCircularDependencyChecker->reset();
        }
        else {
            $this->circularDependencyChecker->reset();
        }
    }

    public function resolveParameter(\ReflectionParameter|\ReflectionProperty $parameter): mixed
    {
        return $this->argumentResolver->resolveParameter($parameter);
    }

    public function resolveMethodParameters(
        \ReflectionMethod|\ReflectionFunction $method,
        array                                 $givenArguments
    ): array
    {
        return $this->argumentResolver->resolveMethodParameters($method, $givenArguments);
    }
}
