<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ParameterResolving;

use Medas\Core\{
    Attributes\Service,
    Exceptions\CouldNotResolveParameter,
    Interfaces\ArgumentProcessor,
    Interfaces\ParameterResolveManager as ManagerInterface,
    Interfaces\ParameterResolver
};

#[Service]
class ParameterResolveManager implements ManagerInterface
{
    /** @var ParameterResolver[]|null */
    private array|null $parameterResolvers = null;

    /** @var ArgumentProcessor[]|null */
    private array|null $argumentProcessors = null;

    /**
     * @param string[] $parameterResolverNames
     * @param string[] $argumentProcessorNames
     */
    public function __construct(
        private readonly array $parameterResolverNames,
        private readonly array $argumentProcessorNames,
    )
    {
        // This service is *not* instantiated automatically,
        // so don't add arguments and expect them to be injected.
    }

    public function resolveMethodParameters(
        \ReflectionMethod|\ReflectionFunction $method,
        array                                 $givenArguments
    ): array
    {
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

    private function processArgument(\ReflectionParameter $parameter, mixed $argument): mixed
    {
        if ($this->argumentProcessors === null) {
            $this->argumentProcessors = array_map(
                fn(string $name) => new $name(),
                $this->argumentProcessorNames
            );

            uasort($this->argumentProcessors, fn($a, $b) => -$a->priority() <=> $b->priority());
        }

        foreach ($this->argumentProcessors as $processor) {
            $argument = $processor->process($parameter, $argument);
        }

        return $argument;
    }

    public function resolveParameter(\ReflectionParameter|\ReflectionProperty $parameter): mixed
    {
        if ($this->parameterResolvers === null) {
            $this->parameterResolvers = array_map(
                fn(string $name) => new $name(),
                $this->parameterResolverNames
            );

            uasort($this->parameterResolvers, fn($a, $b) => -$a->priority() <=> $b->priority());
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
}
