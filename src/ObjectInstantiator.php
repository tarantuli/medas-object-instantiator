<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator;

use Medas\Core\{
    Attributes\Service,
    Exceptions\CircularDependencyFound,
    Exceptions\CouldNotResolveParameter,
    Interfaces\InstantiatesLazily,
    Interfaces\ObjectInstantiator as ObjectInstantiatorInterface
};

/**
 * Creates objects with their constructor arguments resolved, and resolves the arguments of other methods and
 * functions. This is the default object instantiator, which the service manager uses for every service it creates.
 *
 * Resolving the arguments is left to @class(Medas\ObjectInstantiator\ArgumentResolving\ArgumentResolver). This class
 * adds lazy objects and the detection of circular dependencies.
 *
 * Two constants switch on development aids:
 *
 * - `TRACE_CIRCULAR_DEPENDENCIES` makes a circular dependency throw `TracedCircularDependencyFound`, which says where
 *   each class in the cycle was requested. It's checked on every instantiation, and inspects the call stack each
 *   time, so it's meant for debugging only.
 * - `TRACE_INSTANTIATIONS`, set to a file path, logs every service that's created, lazily or not, to that file (see
 *   @class(Medas\ObjectInstantiator\Tracing\InstantiationTrace)). It's only checked in the constructor, so it must be
 *   defined before the service manager is built.
 *
 * @assumes The `ServiceManager` creates this instance itself during bootstrap. It isn't autowired, so new constructor
 *          dependencies won't be injected.
 * @sideEffect Compiled resolution plans are written to the `'default'` cache when the process ends (see
 *             @class(Medas\ObjectInstantiator\ArgumentResolving\Plans\PlanStore)).
 * @sideEffect With `TRACE_INSTANTIATIONS` defined, the constructor registers a shutdown function that appends the log
 *             of the process to that file.
 * @state Keeps track of the classes whose constructor arguments are being resolved, to detect circular dependencies.
 *        One instance serves the whole process.
 * @notFor Getting a service: `instantiate()` creates a new object on every call. Use `service()` for the shared
 *         instance.
 * @see ObjectInstantiatorInterface
 */
#[Service]
readonly class ObjectInstantiator implements ObjectInstantiatorInterface, InstantiatesLazily
{
    private ArgumentResolving\ArgumentResolver $argumentResolver;
    private CircularDependencies\Checker $circularDependencyChecker;
    private CircularDependencies\CheckerWithVerboseTracing $verboseCircularDependencyChecker;
    private Tracing\InstantiationTrace|null $trace;

    /**
     * @param string[] $parameterResolverNames Class names of the `ParameterResolver` implementations, in registration
     *                                         order
     * @param string[] $argumentProcessorNames Class names of the `ArgumentProcessor` implementations, in registration
     *                                         order
     * @param bool     $isDev                  Whether cached resolution plans are checked against the modification
     *                                         times of their source files, so changed classes are picked up without
     *                                         clearing the cache
     */
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
        $this->verboseCircularDependencyChecker = new CircularDependencies\CheckerWithVerboseTracing();

        // Development aid: define TRACE_INSTANTIATIONS as a file path, before the service
        // manager is built, to log every service instantiated (see InstantiationTrace)
        /** @noinspection PhpUndefinedConstantInspection */
        $this->trace = defined('TRACE_INSTANTIATIONS')
            ? new Tracing\InstantiationTrace(TRACE_INSTANTIATIONS)
            : null;
    }

    /**
     * Returns a new instance of `$type`, with its constructor arguments resolved.
     *
     * Values in `$givenArguments`, keyed by parameter name, are used instead of being resolved. They still pass
     * through the argument processors. Keys that match no parameter are ignored. Exceptions thrown by parameter
     * resolvers, like `EnvValueDoesNotExist`, pass through.
     *
     * @throws CircularDependencyFound When `$type` is already being instantiated further up the call stack.
     * @throws CircularDependencies\TracedCircularDependencyFound Instead of `CircularDependencyFound`, when `TRACE_CIRCULAR_DEPENDENCIES`
     *                                       is defined.
     * @throws CouldNotResolveParameter When a constructor parameter isn't given, no resolver handles it, and it has
     *                                  no default value and doesn't allow `null`.
     */
    public function instantiate(string $type, array $givenArguments = []): object
    {
        $this->trace?->created($type);

        return $this->construct($type, $givenArguments);
    }

    /**
     * Returns a lazy ghost: an uninitialised instance of `$type`, whose constructor arguments are resolved and whose
     * constructor is called on first access to one of its properties. Calling a method that doesn't touch properties
     * doesn't initialise it.
     *
     * Types that can't be lazy are instantiated directly: abstract classes, classes without a public constructor with
     * parameters, classes extending an internal PHP class, and classes marked with `#[Service(lazy: false)]`.
     *
     * The exceptions `instantiate()` throws are thrown where the ghost is first used instead, unless the type is
     * instantiated directly. So is any exception from the constructor itself.
     */
    public function instantiateLazy(string $type): object
    {
        if (!$this->argumentResolver->canBeLazy($type)) {
            $this->trace?->createdAlthoughAskedLazily($type);

            return $this->construct($type, []);
        }

        $this->trace?->createdLazily($type);

        return new \ReflectionClass($type)->newLazyGhost($this->initializeGhost(...));
    }

    private function construct(string $type, array $givenArguments): object
    {
        return new $type(...$this->resolveConstructorArguments($type, $givenArguments));
    }

    private function initializeGhost(object $ghost): void
    {
        $this->trace?->initialized($ghost::class);

        $ghost->__construct(...$this->resolveConstructorArguments($ghost::class, []));
    }

    private function resolveConstructorArguments(string $type, array $givenArguments): array
    {
        if (defined('TRACE_CIRCULAR_DEPENDENCIES')) {
            $this->verboseCircularDependencyChecker->check($type);
        }
        else {
            $this->circularDependencyChecker->check($type);
        }

        $this->trace?->startConstructing($type);

        try {
            return $this->argumentResolver->resolveConstructorArguments($type, $givenArguments);
        }

        finally{
            $this->trace?->endConstructing();

            if (defined('TRACE_CIRCULAR_DEPENDENCIES')) {
                $this->verboseCircularDependencyChecker->markComplete($type);
            }
            else {
                $this->circularDependencyChecker->markComplete($type);
            }
        }
    }

    /**
     * Resolves a single parameter or property, through the parameter resolvers only: the argument processors are not
     * applied. When no resolver handles it, its default value is used, or `null` when it allows that.
     *
     * @throws CouldNotResolveParameter When no resolver handles it, and it has no default value and doesn't allow
     *                                  `null`.
     * @performance Asks every resolver, with reflection and attribute reading, on every call.
     */
    public function resolveParameter(\ReflectionParameter|\ReflectionProperty $parameter): mixed
    {
        return $this->argumentResolver->resolveParameter($parameter);
    }

    /**
     * Returns the arguments for `$method`, in parameter order, ready to be spread into a call.
     *
     * Values in `$givenArguments`, keyed by parameter name, are used instead of being resolved. They still pass
     * through the argument processors. Keys that match no parameter are ignored.
     *
     * @throws CouldNotResolveParameter When a parameter isn't given, no resolver handles it, and it has no default
     *                                  value and doesn't allow `null`.
     * @performance Methods are resolved through cached plans. Functions and closures are resolved by asking every
     *              resolver and processor, with reflection and attribute reading, on every call.
     */
    public function resolveMethodParameters(
        \ReflectionMethod|\ReflectionFunction $method,
        array                                 $givenArguments
    ): array
    {
        return $this->argumentResolver->resolveMethodParameters($method, $givenArguments);
    }
}
