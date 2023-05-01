<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\Exceptions;

use Medas\Core\Exceptions\BaseException;

class CouldNotResolveParameter extends BaseException
{
    public function __construct(\ReflectionParameter $parameter)
    {
        parent::__construct(
            '$' . $parameter->name,
            $parameter->getDeclaringClass()->name . '::' . $parameter->getDeclaringFunction()->name,
        );
    }

    public function pattern(): string
    {
        return 'could not resolve parameter %s in %s';
    }
}
