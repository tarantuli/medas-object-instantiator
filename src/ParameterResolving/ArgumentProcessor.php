<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ParameterResolving;

interface ArgumentProcessor
{
    public function process(\ReflectionParameter|\ReflectionProperty $parameter, mixed $argument): mixed;

    public function priority(): int;
}
