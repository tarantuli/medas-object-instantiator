<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ParameterResolving;

use Medas\Core\Attributes\Service;
use Medas\ObjectInstantiator\Exceptions\MultipleImplementorsFoundForParameter;
use Medas\ServiceManager\Exceptions\MultipleImplementorsFound;

#[Service]
class ServiceFinderByType implements ParameterResolver
{
    private object $result;

    public function __construct()
    {
        // This service is *not* instantiated automatically,
        // so don't add more dependencies, expecting them to be injected.
    }

    public function __serialize(): array
    {
        // This is needed to make sure $result isn't serialized
        return [];
    }

    public function __unserialize(array $data): void
    {
        // Do nothing
    }

    public function priority(): int
    {
        return -200;
    }

    public function handle(\ReflectionParameter|\ReflectionProperty $parameter): bool
    {
        $serviceManager = medas()->serviceManager();

        $service = null;
        $types = parameterTypes($parameter);

        foreach ($types as $type) {
            $typeName = $type->getName();

            if (null !== $serviceManager->findImplementingClass($typeName)) {
                $service = $typeName;
                break;
            }
        }

        if (null === $service) {
            return false;
        }

        try {
            $this->result = $serviceManager->resolve($service);
        }
        catch (MultipleImplementorsFound $exception) {
            throw (new MultipleImplementorsFoundForParameter(
                $exception->type,
                $parameter->name,
                $parameter->getDeclaringClass()->name,
                $parameter->getDeclaringFunction()->name,
                $exception->implementors
            ))->setPrevious($exception);
        }

        return true;
    }

    public function result(): object
    {
        $result = $this->result;

        unset($this->result);

        return $result;
    }
}
