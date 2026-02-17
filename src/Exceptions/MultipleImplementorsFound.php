<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\Exceptions;

use Medas\Core\Exceptions\{BaseException, Suggestions};

class MultipleImplementorsFound extends BaseException implements Suggestions
{
    public function __construct(
        public readonly string $type,
        public readonly array  $implementors,
    )
    {
        parent::__construct($type, implode(', ', $implementors));
    }

    public function pattern(): string
    {
        return 'found multiple services implementing %s: %s';
    }

    public function suggestions(): array
    {
        return [
            'bind the one you want to use using sm()->bindImplementation()',
        ];
    }
}
