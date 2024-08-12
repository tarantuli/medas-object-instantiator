<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\Exceptions;

use Medas\Core\Exceptions\BaseException;

class EnvValueDoesNotExist extends BaseException
{
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    public function pattern(): string
    {
        return 'No $_ENV value with name "%s" found';
    }
}
