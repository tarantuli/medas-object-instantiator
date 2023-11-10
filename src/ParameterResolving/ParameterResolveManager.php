<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ParameterResolving;

use Medas\Core\{Attributes\Service, Interfaces\ParameterResolveManager as ManagerInterface};
use Medas\ObjectInstantiator\Exceptions\CouldNotResolveParameter;
use Medas\ServiceManager\ServiceConfig;

#[Service]
class ParameterResolveManager implements ManagerInterface
{
    private ServiceConfig $config;

    public function __construct()
    {
        // This service is *not* instantiated automatically,
        // so don't add more dependencies, expecting them to be injected.
        $serviceManager = medas()->serviceManager();

        $serviceManager->bindImplementation($this, ParameterResolveManager::class);

        $this->config = $serviceManager->config();

        $this->config->addParameterResolver(new ServiceFinderByType());
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

    public function resolveParameter(\ReflectionParameter|\ReflectionProperty $parameter): mixed
    {
        foreach ($this->config->parameterResolvers() as $resolver) {
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

    private function processArgument(\ReflectionParameter $parameter, mixed $argument): mixed
    {
        foreach ($this->config->argumentProcessors() as $processor) {
            $argument = $processor->process($parameter, $argument);
        }

        return $argument;
    }
}
