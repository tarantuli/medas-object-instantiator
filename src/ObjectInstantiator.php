<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator;

use Medas\Core\{
    Attributes\Service,
    Interfaces\InstantiatesLazily,
    Interfaces\ObjectInstantiator as ObjectInstantiatorInterface
};

#[Service]
readonly class ObjectInstantiator implements ObjectInstantiatorInterface, InstantiatesLazily
{
    private ArgumentResolving\ArgumentResolver $argumentResolver;
    private CircularDependencies\Checker $circularDependencyChecker;
    private CircularDependencies\CheckerWithVerboseTracing $verbodeCircularDependencyChecker;

    public function __construct(
        array $parameterResolverNames,
        array $argumentProcessorNames,
        bool  $isDev = false,
    )
    {
        // This service is *not* instantiated automatically,
        // so don't add arguments and expect them to be injected.
        $this->argumentResolver = new ArgumentResolving\ArgumentResolver(
            $parameterResolverNames,
            $argumentProcessorNames,
            $isDev,
        );

        $this->circularDependencyChecker = new CircularDependencies\Checker();
        $this->verbodeCircularDependencyChecker = new CircularDependencies\CheckerWithVerboseTracing();
    }

    public function instantiate(string $type, array $givenArguments = []): object
    {
        return new $type(...$this->resolveConstructorArguments($type, $givenArguments));
    }

    /**
     * Returns a lazy ghost: an uninitialized instance of the type, whose constructor arguments are resolved and whose
     * constructor is called on first access to one of its properties. Calling a method that doesn't touch properties
     * doesn't initialise it.
     *
     * Types that can't be lazy are instantiated directly.
     */
    public function instantiateLazy(string $type): object
    {
        if (!$this->argumentResolver->canBeLazy($type)) {
            return $this->instantiate($type);
        }

        return new \ReflectionClass($type)->newLazyGhost($this->initializeGhost(...));
    }

    private function initializeGhost(object $ghost): void
    {
        $ghost->__construct(...$this->resolveConstructorArguments($ghost::class, []));
    }

    private function resolveConstructorArguments(string $type, array $givenArguments): array
    {
        if (defined('TRACE_CIRCULAR_DEPENDENCIES')) {
            $this->verbodeCircularDependencyChecker->check($type);
        }
        else {
            $this->circularDependencyChecker->check($type);
        }

        try {
            return $this->argumentResolver->resolveConstructorArguments($type, $givenArguments);
        }

        finally{
            if (defined('TRACE_CIRCULAR_DEPENDENCIES')) {
                $this->verbodeCircularDependencyChecker->markComplete($type);
            }
            else {
                $this->circularDependencyChecker->markComplete($type);
            }
        }
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
