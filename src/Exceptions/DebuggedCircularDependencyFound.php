<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\Exceptions;

use Medas\Core\Exceptions\BaseException;

class DebuggedCircularDependencyFound extends BaseException
{
    public function __construct(array $trace, string $current, string $sourceFile)
    {
        $circle = ["$current => $sourceFile"];

        foreach ($trace as $class => $sourceFile) {
            $circle[] = "$class => $sourceFile";
        }

        parent::__construct(implode("\n*  ", $circle));
    }

    public function pattern(): string
    {
        return "circular dependency found:\n*  %s";
    }
}
