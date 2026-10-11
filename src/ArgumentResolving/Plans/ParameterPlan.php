<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving\Plans;

/**
 * The pre-analysed resolution of a single parameter.
 *
 * A step is a tuple of the index of a resolver (or processor) in the priority-ordered list of resolvers (or
 * processors), and the object its ```compile()``` returned for this parameter. Resolvers and processors that returned
 * null from ```compile()``` are left out, because they never apply to this parameter. A step with null as its compiled
 * object belongs to a resolver or processor that is not compilable, which is called the classic way, with a
 * reflection object.
 */
readonly class ParameterPlan
{
    /**
     * @param list<array{int, object|null}> $resolverSteps In order of priority
     * @param list<array{int, object|null}> $processorSteps In order of priority
     */
    public function __construct(
        public string   $name,
        public int      $position,
        public array    $resolverSteps,
        public array    $processorSteps,
        public Fallback $fallback,
        public mixed    $defaultValue = null,
    )
    {
    }

    /**
     * A positional list keeps the cached plans small.
     */
    public function __serialize(): array
    {
        return [
            $this->name,
            $this->position,
            $this->resolverSteps,
            $this->processorSteps,
            $this->fallback->value,
            $this->defaultValue,
        ];
    }

    public function __unserialize(array $data): void
    {
        [
            $this->name,
            $this->position,
            $this->resolverSteps,
            $this->processorSteps,
            $fallback,
            $this->defaultValue,
        ] = $data;

        $this->fallback = Fallback::from($fallback);
    }
}
