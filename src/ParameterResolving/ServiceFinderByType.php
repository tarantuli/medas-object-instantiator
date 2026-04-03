<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ParameterResolving;

use Medas\Core\{
    Attributes\Service,
    Interfaces\ParameterResolver,
    Interfaces\ServiceManager,
    ParameterResolverResult
};
use Medas\ObjectInstantiator\Exceptions\{
    MultipleImplementorsFound,
    MultipleImplementorsFoundForParameter
};

#[Service]
readonly class ServiceFinderByType implements ParameterResolver
{
    public function __construct(
        private ServiceManager $serviceManager,
    )
    {
        // This service is *not* instantiated automatically,
        // so don't add more dependencies, expecting them to be injected.
    }

    public function priority(): int
    {
        return -200;
    }

    public function handle(\ReflectionParameter|\ReflectionProperty $parameter): ParameterResolverResult
    {
        $service = $this->findServiceImplementingTypes($parameter);

        if (null === $service) {
            return new ParameterResolverResult(false);
        }

        try {
            $result = $this->serviceManager->resolve($service);
        }
        catch (MultipleImplementorsFound $exception) {
            throw new MultipleImplementorsFoundForParameter(
                $exception->type,
                $parameter->name,
                $parameter->getDeclaringClass()->name,
                $parameter->getDeclaringFunction()->name,
                $exception->implementors,
                $exception
            );
        }

        return new ParameterResolverResult(true, $result);
    }

    private function findServiceImplementingTypes(\ReflectionParameter|\ReflectionProperty $parameter): string|null
    {
        $types = parameterTypes($parameter);

        foreach ($types as $type) {
            $typeName = $type->getName();

            if (null !== $this->serviceManager->findImplementingClass($typeName)) {
                return $typeName;
            }
        }

        return null;
    }
}
