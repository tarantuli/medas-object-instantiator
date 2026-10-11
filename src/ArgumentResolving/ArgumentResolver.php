<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving;

use Medas\Core\{
    Attributes\Service,
    Exceptions\CouldNotResolveParameter,
    Interfaces\ArgumentProcessor,
    Interfaces\ParameterResolver
};

/**
 * Resolves the arguments for method parameters, using the registered parameter resolvers and argument processors.
 *
 * Methods (constructors included) are resolved using cached plans (see @class(Plans\PlanStore)), so reflection and
 * attribute reading happen once per method instead of on every call. Closures, functions and properties have no
 * stable identity to cache a plan for, so they are resolved by asking every resolver and processor directly.
 */
#[Service]
class ArgumentResolver
{
    /** @var list<ParameterResolver>|null In order of priority */
    private array|null $parameterResolvers = null;

    /** @var list<ArgumentProcessor>|null In order of priority */
    private array|null $argumentProcessors = null;

    private Plans\PlanStore|null $planStore = null;

    /**
     * @param string[] $parameterResolverNames
     * @param string[] $argumentProcessorNames
     */
    public function __construct(
        private readonly array $parameterResolverNames,
        private readonly array $argumentProcessorNames,
        private readonly bool  $isDev = false,
    )
    {
        // This service is *not* instantiated automatically,
        // so don't add arguments and expect them to be injected.
    }

    /**
     * Returns the constructor arguments for the given class, or an empty array if it has no constructor.
     */
    public function resolveConstructorArguments(string $className, array $givenArguments): array
    {
        return $this->resolveByPlan(
            $this->planStore()->get($className, '__construct'),
            $givenArguments
        );
    }

    /**
     * Whether the given class can be instantiated as a lazy object (see @class(Plans\PlanCompiler)).
     */
    public function canBeLazy(string $className): bool
    {
        return $this->planStore()->canBeLazy($className);
    }

    public function resolveMethodParameters(
        \ReflectionMethod|\ReflectionFunction $method,
        array                                 $givenArguments
    ): array
    {
        // Closure::__invoke stands for every closure, so it can't be keyed on its name
        if ($method instanceof \ReflectionMethod && $method->class !== \Closure::class) {
            return $this->resolveByPlan(
                $this->planStore()->get($method->class, $method->name),
                $givenArguments
            );
        }

        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            if (array_key_exists($parameter->name, $givenArguments)) {
                $argument = $givenArguments[$parameter->name];
            }
            else {
                $argument = $this->resolveParameter($parameter);
            }

            $arguments[] = $this->processArgument($parameter, $argument);
        }

        return $arguments;
    }

    private function resolveByPlan(Plans\MethodPlan $plan, array $givenArguments): array
    {
        $arguments = [];

        foreach ($plan->parameters as $parameter) {
            if (array_key_exists($parameter->name, $givenArguments)) {
                $argument = $givenArguments[$parameter->name];
            }
            else {
                $argument = $this->resolvePlannedParameter($plan, $parameter);
            }

            foreach ($parameter->processorSteps as [$processorIndex, $compiled]) {
                $processor = $this->argumentProcessors[$processorIndex];

                $argument = $compiled === null
                    ? $processor->process($plan->reflectParameter($parameter), $argument)
                    : $processor->processCompiled($compiled, $argument);
            }

            $arguments[] = $argument;
        }

        return $arguments;
    }

    private function resolvePlannedParameter(Plans\MethodPlan $plan, Plans\ParameterPlan $parameter): mixed
    {
        foreach ($parameter->resolverSteps as [$resolverIndex, $compiled]) {
            $resolver = $this->parameterResolvers[$resolverIndex];

            $resolveResult = $compiled === null
                ? $resolver->handle($plan->reflectParameter($parameter))
                : $resolver->resolveCompiled($compiled);

            if ($resolveResult->handled) {
                return $resolveResult->result;
            }
        }

        return match ($parameter->fallback) {
            Plans\Fallback::DefaultValue => $parameter->defaultValue,

            Plans\Fallback::DefaultValueFromReflection
                => $plan->reflectParameter($parameter)->getDefaultValue(),

            Plans\Fallback::null => null,

            Plans\Fallback::Fail
                => throw new CouldNotResolveParameter($plan->reflectParameter($parameter)),
        };
    }

    private function planStore(): Plans\PlanStore
    {
        if ($this->planStore === null) {
            if ($this->parameterResolvers === null) {
                $this->loadParameterResolvers();
            }

            if ($this->argumentProcessors === null) {
                $this->loadArgumentProcessors();
            }

            // Plans refer to resolvers and processors by their position, so they are only valid for this exact order
            $this->planStore = new Plans\PlanStore(
                new Plans\PlanCompiler(
                    $this->parameterResolvers,
                    $this->argumentProcessors,
                    $this->isDev
                ),
                array_map(get_class(...), $this->parameterResolvers),
                array_map(get_class(...), $this->argumentProcessors),
                $this->isDev,
            );
        }

        return $this->planStore;
    }

    private function processArgument(\ReflectionParameter|\ReflectionProperty $parameter, mixed $argument): mixed
    {
        if ($this->argumentProcessors === null) {
            $this->loadArgumentProcessors();
        }

        foreach ($this->argumentProcessors as $processor) {
            $argument = $processor->process($parameter, $argument);
        }

        return $argument;
    }

    private function loadArgumentProcessors(): void
    {
        $this->argumentProcessors = $this->instantiateByPriority($this->argumentProcessorNames);
    }

    public function resolveParameter(\ReflectionParameter|\ReflectionProperty $parameter): mixed
    {
        if ($this->parameterResolvers === null) {
            $this->loadParameterResolvers();
        }

        foreach ($this->parameterResolvers as $resolver) {
            $resolveResult = $resolver->handle($parameter);

            if ($resolveResult->handled) {
                return $resolveResult->result;
            }
        }

        if ($parameter instanceof \ReflectionProperty && $parameter->hasDefaultValue()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter instanceof \ReflectionParameter && $parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->allowsNull()) {
            return null;
        }

        throw new CouldNotResolveParameter($parameter);
    }

    private function loadParameterResolvers(): void
    {
        $this->parameterResolvers = $this->instantiateByPriority($this->parameterResolverNames);
    }

    /**
     * Instantiates the given classes and orders them from highest to lowest priority.
     *
     * @param string[] $classNames
     */
    private function instantiateByPriority(array $classNames): array
    {
        $instances = array_map(fn(string $className) => new $className(), $classNames);

        // usort() is stable, so classes with equal priorities keep their registration order
        usort($instances, fn($a, $b) => -($a->priority() <=> $b->priority()));

        return $instances;
    }
}
