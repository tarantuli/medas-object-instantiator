<?php

declare(strict_types=1);

namespace Medas\ObjectInstantiator\ArgumentResolving\Plans;

use Medas\Core\Interfaces\{Cache, CacheManager};

/**
 * Holds the method plans, compiling them on first use.
 *
 * All plans live in a single cache entry, which is read once per request. Inside that entry each plan is serialized
 * separately, so a request only pays for unserializing the plans it actually uses.
 *
 * Plans are compiled lazily rather than all at once when the service configuration is built, so a problem in a
 * service that is never used (e.g. an ambiguous dependency) only surfaces when it is used, as before. Newly compiled
 * plans are written back once, at the end of the request. Two concurrent requests that both compile plans can
 * overwrite each other's additions; the missing plans are simply compiled again on a later request.
 *
 * The plans depend on the registered resolvers and processors and on the service mapping. They share their life
 * cycle with the cached service configuration (both live in the default cache), and the fingerprint discards them
 * when the resolvers or processors change. In development mode, each plan is checked against the modification times
 * of its source files the first time it is used in a request.
 */
class PlanStore
{
    /**
     * Change this when the structure of the plans changes, so plans cached by an older version are discarded.
     */
    private const string FORMAT_VERSION = '1';
    private const array CACHE_KEY = [self::class];

    /** @var array<string, MethodPlan> */
    private array $plans = [];

    /** @var array<string, string>|null */
    private array|null $serializedPlans = null;

    private Cache|null $cache = null;
    private bool $isDirty = false;
    private readonly string $fingerprint;

    /**
     * @param string[] $parameterResolverNames In order of priority, as plans refer to them by position
     * @param string[] $argumentProcessorNames In order of priority, as plans refer to them by position
     */
    public function __construct(
        private readonly PlanCompiler $compiler,
        array                         $parameterResolverNames,
        array                         $argumentProcessorNames,
        private readonly bool         $isDev,
    )
    {
        $this->fingerprint = sha1(self::FORMAT_VERSION . "\0" . implode(
            ',',
            $parameterResolverNames
        ) . "\0" . implode(',', $argumentProcessorNames));
    }

    public function get(string $className, string $methodName): MethodPlan
    {
        $key = $className . '::' . $methodName;

        if (isset($this->plans[$key])) {
            return $this->plans[$key];
        }

        if ($this->serializedPlans === null) {
            $this->load();
        }

        $plan = isset($this->serializedPlans[$key])
            ? unserialize($this->serializedPlans[$key])
            : null;

        // Anything else means the plan was corrupted, or refers to a class that no longer exists
        if (!$plan instanceof MethodPlan) {
            $plan = null;
        }

        // In development mode, a plan is checked once per request, when it's taken from the cache
        if ($plan === null || ($this->isDev && !$plan->isFresh())) {
            $plan = $this->compiler->compile($className, $methodName);
            $this->serializedPlans[$key] = serialize($plan);

            $this->markDirty();
        }

        return $this->plans[$key] = $plan;
    }

    private function load(): void
    {
        // Set this before touching the cache, so that resolving the cache manager can never end up here again
        $this->serializedPlans = [];
        $this->cache = service(CacheManager::class)->get();
        $stored = $this->cache->get(self::CACHE_KEY, static fn() => null);

        if (is_array($stored) && ($stored['fingerprint'] ?? null) === $this->fingerprint) {
            $this->serializedPlans += $stored['plans'];
        }
    }

    private function markDirty(): void
    {
        if ($this->isDirty) {
            return;
        }

        $this->isDirty = true;

        register_shutdown_function($this->save(...));
    }

    /**
     * Writes the plans to the cache. Called automatically at the end of the request when plans were compiled.
     */
    public function save(): void
    {
        if (!$this->isDirty || $this->cache === null) {
            return;
        }

        $this->isDirty = false;

        try {
            $this->cache->set(self::CACHE_KEY, [
                'fingerprint' => $this->fingerprint,
                'plans' => $this->serializedPlans,
            ]);
        }
        catch (\Throwable $exception) {
            // Failing to cache the plans only costs performance, so it must not break a request that has otherwise
            // been handled already
            error_log('Could not cache argument resolution plans: ' . $exception->getMessage());
        }
    }
}
