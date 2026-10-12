<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving\Plans;

use Medas\Core\{
    Attributes\Service,
    Interfaces\ArgumentProcessor,
    Interfaces\CompilableArgumentProcessor,
    Interfaces\CompilableParameterResolver,
    Interfaces\DependsOnSourceFiles,
    Interfaces\ParameterResolver
};

/**
 * Analyses the parameters of a method once, so they can be resolved without reflection from then on. This is where
 * all attribute reading of parameter resolvers and argument processors happens.
 */
readonly class PlanCompiler
{
    /**
     * @param list<ParameterResolver> $parameterResolvers In order of priority
     * @param list<ArgumentProcessor> $argumentProcessors In order of priority
     * @param bool $isDev Whether to record the source files, to detect stale plans
     */
    public function __construct(
        private array $parameterResolvers,
        private array $argumentProcessors,
        private bool  $isDev,
    )
    {
    }

    public function compile(string $className, string $methodName): MethodPlan
    {
        $class = new \ReflectionClass($className);
        $sourceFiles = [];

        $this->addSourceFile($class->getFileName(), $sourceFiles);

        $method = $methodName === '__construct'
            ? $class->getConstructor()
            : $class->getMethod($methodName);

        if ($method === null) {
            return new MethodPlan($className, $methodName, [], $sourceFiles);
        }

        // The method can be declared in a parent class or a trait, which lives in another file
        $this->addSourceFile($method->getFileName(), $sourceFiles);

        $parameters = [];

        foreach ($method->getParameters() as $parameter) {
            $parameters[] = $this->compileParameter($parameter, $sourceFiles);
        }

        return new MethodPlan(
            $className,
            $methodName,
            $parameters,
            $sourceFiles,
            $method->isConstructor() && $this->canBeLazy($class, $method),
        );
    }

    private function compileParameter(\ReflectionParameter $parameter, array &$sourceFiles): ParameterPlan
    {
        [$fallback, $defaultValue] = $this->determineFallback($parameter);
        $resolverSteps = $this->compileResolverSteps($parameter);
        $processorSteps = $this->compileProcessorSteps($parameter);

        // A compiled step can depend on other classes (a config option's name and default, for
        // instance), and the plan is stale when one of those changes too
        foreach ([...$resolverSteps, ...$processorSteps] as [, $compiled]) {
            if ($compiled instanceof DependsOnSourceFiles) {
                foreach ($compiled->sourceFiles() as $fileName) {
                    $this->addSourceFile($fileName, $sourceFiles);
                }
            }
        }

        return new ParameterPlan(
            $parameter->name,
            $parameter->getPosition(),
            $resolverSteps,
            $processorSteps,
            $fallback,
            $defaultValue,
        );
    }

    /**
     * @return array{Fallback, mixed}
     */
    private function determineFallback(\ReflectionParameter $parameter): array
    {
        if ($parameter->isDefaultValueAvailable()) {
            // A constant can be defined differently per request, and an object must be a new instance every time,
            // so neither can be stored in the plan
            if ($parameter->isDefaultValueConstant()) {
                return [Fallback::DefaultValueFromReflection, null];
            }

            $defaultValue = $parameter->getDefaultValue();

            if ($this->containsObject($defaultValue)) {
                return [Fallback::DefaultValueFromReflection, null];
            }

            return [Fallback::DefaultValue, $defaultValue];
        }

        if ($parameter->allowsNull()) {
            return [Fallback::null, null];
        }

        return [Fallback::Fail, null];
    }

    private function containsObject(mixed $value): bool
    {
        // Enum cases are immutable singletons, they survive serialization as the same instance
        if (is_object($value)) {
            return !$value instanceof \UnitEnum;
        }

        if (is_array($value)) {
            return array_any($value, fn(mixed $item): bool => $this->containsObject($item));
        }

        return false;
    }

    private function compileResolverSteps(\ReflectionParameter $parameter): array
    {
        $steps = [];

        foreach ($this->parameterResolvers as $index => $resolver) {
            if (!$resolver instanceof CompilableParameterResolver) {
                $steps[] = [$index, null];

                continue;
            }

            $compiled = $resolver->compile($parameter);

            if ($compiled !== null) {
                $steps[] = [$index, $compiled];
            }
        }

        return $steps;
    }

    private function compileProcessorSteps(\ReflectionParameter $parameter): array
    {
        $steps = [];

        foreach ($this->argumentProcessors as $index => $processor) {
            if (!$processor instanceof CompilableArgumentProcessor) {
                $steps[] = [$index, null];

                continue;
            }

            $compiled = $processor->compile($parameter);

            if ($compiled !== null) {
                $steps[] = [$index, $compiled];
            }
        }

        return $steps;
    }

    private function addSourceFile(string|false $fileName, array &$sourceFiles): void
    {
        // Internal classes have no file, eval()'d classes have no real one
        if (!$this->isDev || $fileName === false || isset($sourceFiles[$fileName]) || !is_file($fileName)) {
            return;
        }

        $sourceFiles[$fileName] = filemtime($fileName);
    }

    /**
     * A lazy object only pays off when the constructor has dependencies to resolve, and PHP can't make objects lazy
     * that extend internal classes, or call a constructor that isn't public from the outside.
     */
    private function canBeLazy(\ReflectionClass $class, \ReflectionMethod $constructor): bool
    {
        if ($class->isAbstract() || !$constructor->isPublic() || $constructor->getNumberOfParameters() === 0) {
            return false;
        }

        if (attribute(Service::class, $class)?->lazy === false) {
            return false;
        }

        for ($ancestor = $class; $ancestor !== false; $ancestor = $ancestor->getParentClass()) {
            if ($ancestor->isInternal()) {
                return false;
            }
        }

        return true;
    }
}
