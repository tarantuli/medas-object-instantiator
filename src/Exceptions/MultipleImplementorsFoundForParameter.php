<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\Exceptions;

use Medas\Core\Exceptions\BaseException;
use Medas\Core\Exceptions\Suggestions;

class MultipleImplementorsFoundForParameter extends BaseException implements Suggestions
{

    public function __construct(
        string $type,
        string $parameter,
        string $class,
        string $method,
        array  $implementors,
    )
    {
        parent::__construct($type, $parameter, $class, $method, implode(', ', $implementors));
    }

    public function pattern(): string
    {
        return 'Found multiple services implementing %s for parameter %s of %s:%s(): %s';
    }

    public function suggestions(): array
    {
        return [
            'bind the one you want to use using sm()->bindImplementation()',
        ];
    }
}
