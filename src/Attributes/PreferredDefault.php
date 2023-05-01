<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class PreferredDefault
{
    public function __construct(
        public readonly string $className,
    )
    {
    }
}
