<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ParameterResolving;

interface ParameterResolver
{
    public function priority(): int;

    public function handle(\ReflectionParameter|\ReflectionProperty $parameter): bool;

    public function result(): mixed;
}
