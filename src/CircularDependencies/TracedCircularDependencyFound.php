<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\CircularDependencies;

use Medas\Core\{Exceptions\BaseException, Interfaces\DeclaresMaxStringLength};

class TracedCircularDependencyFound extends BaseException implements DeclaresMaxStringLength
{
    /** @param VerboseFrame[]  $frames */
    public function __construct(array $frames, string $requestedType, string $source)
    {
        $history = '';

        foreach ($frames as $frame) {
            if ($frame->completed) {
                continue;
            }

            $history .= sprintf("   -  %s   from %s\n", $frame->type, $frame->source);
        }

        parent::__construct("triggered by instantiation request for $requestedType from $source\n\n" . $history);
    }

    public function pattern(): string
    {
        return "%s";
    }

    public function maxStringLength(): int
    {
        return 10000;
    }
}
