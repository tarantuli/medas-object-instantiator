# medas-object-instantiator

Part of the [Medas framework](https://github.com/tarantuli/medas-core).

## Description

The dependency injection engine of the Medas framework. `ObjectInstantiator` resolves constructor arguments for any class and instantiates it, delegating parameter resolution to a prioritised chain of `ParameterResolver` implementations, and post-processing the resolved arguments with a chain of `ArgumentProcessor` implementations.

**Resolution order** (the highest priority first):

| Resolver                                                                                            | Priority | Handles                                                                   |
|-----------------------------------------------------------------------------------------------------|----------|---------------------------------------------------------------------------|
| Registered `ParameterResolver` plugins (e.g. `#[ConfigValue]`, `#[CookieValue]`, `#[BodyArgument]`) | varies   | Custom attribute-based injection                                          |
| `EnvValueResolver`                                                                                  | −180     | `#[EnvValue('NAME')]` — injects `$_ENV['NAME']` directly                  |
| `PreferredDefaultFinder`                                                                            | −190     | `#[PreferredDefault(SomeClass::class)]` — overrides the type-based lookup |
| `ServiceFinderByType`                                                                               | −200     | Resolves by type name via `ServiceManager::findImplementingClass()`       |
| PHP default value                                                                                   | —        | Falls back to the declared default if available                           |
| `null`                                                                                              | —        | Injected when the parameter type permits `null` and nothing else resolves |
| `CouldNotResolveParameter`                                                                          | —        | Thrown when no resolver succeeds and no fallback exists                   |

The package registers `ServiceFinderByType`, `EnvValueResolver` and `PreferredDefaultFinder` during `initialize()`.

**Compiled resolution plans** — the first time a method's arguments are resolved, `PlanCompiler` analyses its parameters once: which resolvers and processors can ever apply to each parameter (this is where all attribute reading happens), and what the fallback is. The result, a `MethodPlan`, is cached by `PlanStore`, so later resolutions need no reflection and only call the resolvers that apply. A typical service dependency is resolved with a single call to `ServiceFinderByType`.

- All plans live in one entry in the default cache, read once per request; each plan inside it is unserialized only when used.
- Plans are compiled lazily, on first use, and written back to the cache once, at the end of the request.
- They are discarded automatically when the registered resolvers or processors change. They share their life cycle with the cached service configuration, so clear the cache on deploy (post-install does this).
- In development mode (`isDev` in the service configuration), each plan is checked against the modification times of its source files the first time it is used in a request, so changing a constructor takes effect without clearing the cache.
- Closures, functions and properties have no stable identity to key a plan on; they are resolved by asking every resolver directly.

**Lazy services** — services injected as dependencies are lazy objects by default (PHP 8.4 lazy ghosts, see `InstantiatesLazily`): the object exists and has its real class, but its constructor arguments are only resolved, and its constructor only called, on first access to one of its properties. A service that is injected but never used is never constructed, and neither are its own dependencies. See [Lazy services](#lazy-services).

**Circular dependency detection** — `ObjectInstantiator` tracks the classes whose constructor arguments are being resolved and throws `CircularDependencyFound` when a class is encountered twice. Because dependencies are injected lazily, a cycle through injected services (A needs B, B needs A) is not a problem: B receives A without A's constructor having to run first. Define `TRACE_CIRCULAR_DEPENDENCIES` to get `TracedCircularDependencyFound` instead, which describes where each class in the cycle was requested.

## Usage

### Package developer context

Register the package:

```php
use Medas\ObjectInstantiator\ObjectInstantiatorPackage;

ObjectInstantiatorPackage::instance();
```

**Instantiating a class with automatic DI:**

```php
use Medas\Core\Interfaces\ObjectInstantiator;
use Medas\Core\Attributes\Service;

#[Service]
readonly class ServiceFactory
{
    public function __construct(
        private ObjectInstantiator $instantiator,
    ) {}

    public function make(string $className): object
    {
        // All constructor parameters resolved automatically
        return $this->instantiator->instantiate($className);
    }

    public function makeWith(string $className, array $given): object
    {
        // Named arguments in $given are injected directly;
        // remaining parameters are resolved automatically
        return $this->instantiator->instantiate($className, $given);
    }
}
```

**Injecting an environment variable directly:**

```php
use Medas\Core\Attributes\{EnvValue, Service};

#[Service]
readonly class ApiClient
{
    public function __construct(
        #[EnvValue('THIRD_PARTY_API_KEY')]
        private string $apiKey,
    ) {}
}
```

`EnvValueDoesNotExist` is thrown at instantiation time if the key is absent from `$_ENV`. Load your `.env` file via `medas-config-manager` before the DI container is built.

**Overriding type-based resolution with `#[PreferredDefault]`:**

```php
use Medas\Core\Attributes\{PreferredDefault, Service};

// When multiple implementations of CacheInterface exist,
// explicitly select one for this parameter
#[Service]
readonly class ReportGenerator
{
    public function __construct(
        #[PreferredDefault(ApcuCache::class)]
        private CacheInterface $cache,
    ) {}
}
```

**Writing a custom `ParameterResolver`:**

Resolvers are instantiated with `new`, without constructor arguments, because they are part of the dependency injection machinery itself; resolve the services they need inside their methods.

Implement `CompilableParameterResolver` so the resolver takes part in the compiled plans. `compile()` runs once per parameter and does all the reflection: it returns a small, serializable object with what the resolver needs to know about the parameter, or `null` when the resolver never applies to it. `resolveCompiled()` runs on every resolution, with only that object. The `HandlesByCompiling` trait implements the classic `handle()` on top of these two, for closures and properties.

```php
use Medas\Core\{CompiledValue, HandlesByCompiling, ParameterResolverResult};
use Medas\Core\Interfaces\CompilableParameterResolver;

readonly class CurrentUserResolver implements CompilableParameterResolver
{
    use HandlesByCompiling;

    public function priority(): int
    {
        // Run before ServiceFinderByType (−200)
        return -150;
    }

    public function compile(\ReflectionParameter|\ReflectionProperty $parameter): CompiledValue|null
    {
        foreach (normalizeType($parameter->getType()) as $type) {
            if ($type->getName() === User::class) {
                // Nothing else to remember about the parameter
                return new CompiledValue(true);
            }
        }

        // This resolver never applies to this parameter, so it won't be asked again
        return null;
    }

    public function resolveCompiled(object $compiled): ParameterResolverResult
    {
        return new ParameterResolverResult(true, service(AuthService::class)->currentUser());
    }
}
```

A resolver can still return an unhandled result from `resolveCompiled()` (e.g. a value missing from this particular request), in which case the next resolver is tried. Resolvers that only implement `ParameterResolver` keep working; they are asked for every parameter, with a reflection object.

`ArgumentProcessor` implementations work the same way: implement `CompilableArgumentProcessor` (`compile()` and `processCompiled()`) and use the `ProcessesByCompiling` trait.

Register resolvers and processors by class name in your package's `initialize()`:

```php
$config->addParameterResolver(CurrentUserResolver::class);
$config->addArgumentProcessor(MyArgumentProcessor::class);
```

**Resolving a single parameter manually:**

```php
$reflection = new \ReflectionClass(MyService::class);
$parameter  = $reflection->getConstructor()->getParameters()[0];

$value = $instantiator->resolveParameter($parameter);
```

**Debugging circular dependencies:**

```php
// Define before the DI container is built
define('TRACE_CIRCULAR_DEPENDENCIES', true);
```

With this constant defined, `TracedCircularDependencyFound` is thrown instead of `CircularDependencyFound`, and it describes where each class in the cycle was requested.

### Lazy services

`ServiceFinderByType` injects services through `ServiceManager::resolveLazy()`, which registers a lazy object as the service when it hasn't been instantiated yet; later calls to `resolve()` return that same object. Calling a method that doesn't touch any property doesn't initialize it.

A class is only made lazy when that pays off and PHP allows it: it must have a public constructor with at least one parameter, must not be abstract and must not extend an internal PHP class (such as `ArrayObject` or `Exception`). Other classes are instantiated directly.

Things to keep in mind:

- An exception thrown by a service's constructor surfaces where the service is first used, not where it is injected.
- A constructor's side effects only happen when the service is first used. Mark services whose constructor must run when they are injected with `#[Service(lazy: false)]`.
- Class constants and property defaults are evaluated when the lazy object is created. If they depend on something a constructor sets up (such as constants defined at runtime), set that up elsewhere, e.g. in the package's `ready()`.

### Backend user context

Object instantiation and DI are fully automatic once packages are registered — no manual wiring is needed for classes annotated with `#[Service]`. The instantiator is most commonly used directly only in two situations:

**Testing** — instantiate a class with specific mock arguments while letting everything else be resolved automatically:

```php
$service = $instantiator->instantiate(InvoiceService::class, [
    'repository' => $mockRepository,
]);
```

**Dynamic class creation** — when the class name is only known at runtime (e.g. from a config value or command-line argument):

```php
$handlerClass = $config->getValue('payment.handler-class');
$handler = $instantiator->instantiate($handlerClass);
```

**Caching** — after deploying new code, clear the cache (post-install does this), so plans compiled for the old code are not used. In development mode, changed classes are detected automatically.
