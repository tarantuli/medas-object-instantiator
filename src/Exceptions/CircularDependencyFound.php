<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\Exceptions;

use Medas\Core\Exceptions\BaseException;

class CircularDependencyFound extends BaseException
{
    public function __construct(array $trace, string $current)
    {
        $currentInTrace = array_search($current, $trace);
        $circle = array_merge(array_slice($trace, $currentInTrace), [$current]);

        parent::__construct(implode("\n*  ", $circle));
    }

    public function pattern(): string
    {
        return "circular dependency found:\n*  %s";
    }
}
