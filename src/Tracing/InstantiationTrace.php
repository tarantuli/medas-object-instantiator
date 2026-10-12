<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\Tracing;

// Records, for one request or command, every service the object instantiator creates:
// directly, as a lazy ghost, or a ghost that got initialised. Each line says who asked
// for it (the service whose constructor needed it, or else the first caller outside the
// instantiator and service manager), or for an initialised ghost, who first used it.
// Written to the file when the process ends, appended, so several requests can be
// compared. Switched on by defining TRACE_INSTANTIATIONS as that file's path.
class InstantiationTrace
{
    private const array FRAMEWORK_CLASS_PREFIXES = [
        'Medas\\ObjectInstantiator\\',
        'Medas\\ServiceManager\\ServiceManager',
    ];
    private const array FRAMEWORK_FUNCTIONS = ['service', 'sm', 'medas'];

    /** @var string[] The types whose constructor arguments are being resolved, innermost last. */
    private array $constructing = [];

    /** @var array<int, array{string, string, string}> */
    private array $events = [];

    public function __construct(
        private readonly string $file,
    )
    {
        register_shutdown_function($this->write(...));
    }

    private function write(): void
    {
        $counts = array_count_values(array_column($this->events, 0));

        $request = isset($_SERVER['REQUEST_METHOD'])
            ? $_SERVER['REQUEST_METHOD'] . ' ' . ($_SERVER['REQUEST_URI'] ?? '')
            : implode(' ', $_SERVER['argv'] ?? []);

        $lines = [sprintf(
            '=== %s %s: %d created, %d created although asked lazily, %d lazy, %d of them initialized',
            date('Y-m-d H:i:s'),
            $request,
            $counts['created'] ?? 0,
            $counts['created, not lazy'] ?? 0,
            $counts['lazy'] ?? 0,
            $counts['initialized'] ?? 0,
        )];

        foreach ($this->events as [$event, $type, $by]) {
            $lines[] = $event . "\t" . $type . "\t" . $by;
        }

        file_put_contents(
            $this->file,
            implode(PHP_EOL, $lines) . PHP_EOL . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function created(string $type): void
    {
        $this->events[] = ['created', $type, $this->requester()];
    }

    // Asked for lazily, but its class can't be lazy (see ArgumentResolver::canBeLazy())
    public function createdAlthoughAskedLazily(string $type): void
    {
        $this->events[] = ['created, not lazy', $type, $this->requester()];
    }

    public function createdLazily(string $type): void
    {
        $this->events[] = ['lazy', $type, $this->requester()];
    }

    // The service being built when its constructor needs this one, or else the first
    // caller outside the instantiator and the service manager.
    private function requester(): string
    {
        if ($this->constructing !== []) {
            return end($this->constructing) . '::__construct';
        }

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if (!$this->isFramework($frame)) {
                return $this->describe($frame);
            }
        }

        return '(unknown)';
    }

    private function isFramework(array $frame): bool
    {
        if (!isset($frame['class'])) {
            return in_array($frame['function'] ?? '', self::FRAMEWORK_FUNCTIONS, true);
        }

        return array_any(
            self::FRAMEWORK_CLASS_PREFIXES,
            fn($prefix) => str_starts_with($frame['class'], $prefix)
        );
    }

    // Called from the ghost's initialiser: the frame above it is where the ghost was touched
    public function initialized(string $type): void
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $this->events[] = ['initialized', $type, $this->describe($frames[2] ?? [])];
    }

    private function describe(array $frame): string
    {
        if ($frame === []) {
            return '(top level)';
        }

        return isset($frame['class'])
            ? $frame['class'] . '->' . $frame['function']
            : $frame['function'];
    }

    public function startConstructing(string $type): void
    {
        $this->constructing[] = $type;
    }

    public function endConstructing(): void
    {
        array_pop($this->constructing);
    }
}
