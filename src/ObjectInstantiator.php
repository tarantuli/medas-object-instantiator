<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator;

use Medas\Core\Attributes\Service;
use Medas\Core\Interfaces\ObjectInstantiator as ObjectInstantiatorInterface;

#[Service]
class ObjectInstantiator implements ObjectInstantiatorInterface
{
    private ParameterResolving\ParameterResolveManager $parameterResolveManager;

    /** @var string[] */
    private array $instantiating = [];

    public function __construct()
    {
        // This service is *not* instantiated automatically,
        // so don't add arguments and expect them to be injected.

        $this->parameterResolveManager = new ParameterResolving\ParameterResolveManager();
    }

    public function instantiate(string $type, array $givenArguments = []): object
    {
        $this->checkCircularDependencies($type);
        $arguments = $this->getConstructorArgumentValues($type, $givenArguments);
        unset($this->instantiating[$type]);

        return new $type(...$arguments);
    }

    private function checkCircularDependencies(string $className): void
    {
        if (defined('DEBUG_CIRCULAR_DEPENDENCIES')) {
            $this->checkCircularDependenciesWithAdditionalDebugging($className);
        }
        else {
            if (isset($this->instantiating[$className])) {
                throw new Exceptions\CircularDependencyFound(array_keys($this->instantiating), $className);
            }

            $this->instantiating[$className] = true;
        }
    }

    private function checkCircularDependenciesWithAdditionalDebugging(string $className): void
    {
        foreach (array_reverse(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)) as $trace) {
            if (str_starts_with($trace['file'], __NAMESPACE__ . '\\')) {
                continue;
            }

            if (isset($this->instantiating[$className])) {
                throw new Exceptions\DebuggedCircularDependencyFound($this->instantiating, $className, $trace['file'] . ':' . $trace['line']);
            }

            $this->instantiating[$className] = $trace['file'] . ':' . $trace['line'];

            return;
        }
    }

    private function getConstructorArgumentValues(string $className, array $givenArguments): array
    {
        $class = new \ReflectionClass($className);

        if (!$constructor = $class->getConstructor()) {
            return [];
        }

        return $this->parameterResolveManager->resolveMethod($constructor, $givenArguments);
    }

    public function resolveParameter(\ReflectionParameter|\ReflectionProperty $parameter): mixed
    {
        return $this->parameterResolveManager->resolveParameter($parameter);
    }

    public function resetInstantiatingStack(): void
    {
        $this->instantiating = [];
    }
}
