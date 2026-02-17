<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\Exceptions;

use Medas\Core\Exceptions\BaseException;

class CouldNotResolveParameter extends BaseException
{
    public function __construct(\ReflectionParameter $parameter)
    {
        $declaringClass = $parameter->getDeclaringClass();

        if ($declaringClass) {
            $message = $declaringClass->name . '::' . $parameter->getDeclaringFunction()->name;
        }
        else {
            $message = $parameter->getDeclaringFunction()->name;
        }

        parent::__construct(
            '$' . $parameter->name,
            $message,
        );
    }

    public function pattern(): string
    {
        return 'could not resolve parameter %s in %s';
    }
}
