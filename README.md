# medas-object-instantiator

Part of the [Medas framework](https://github.com/tarantuli/medas-core).

## Description

The dependency injection engine of the Medas framework. `ObjectInstantiator` resolves constructor arguments for any class and instantiates it, delegating parameter resolution to a prioritised chain of `ParameterResolver` implementations.

**Resolution order** (the highest priority first):

| Resolver                                                                                            | Priority | Handles                                                                   |
|-----------------------------------------------------------------------------------------------------|----------|---------------------------------------------------------------------------|
| Registered `ParameterResolver` plugins (e.g. `#[ConfigValue]`, `#[CookieValue]`, `#[BodyArgument]`) | varies   | Custom attribute-based injection                                          |
| `PreferredDefaultFinder`                                                                            | −190     | `#[PreferredDefault(SomeClass::class)]` — overrides the type-based lookup |
| `EnvValueResolver`                                                                                  | −180     | `#[EnvValue('NAME')]` — injects `$_ENV['NAME']` directly                  |
| `ServiceFinderByType`                                                                               | −200     | Resolves by type name via `ServiceManager::findImplementingClass()`       |
| PHP default value                                                                                   | —        | Falls back to the declared default if available                           |
| `null`                                                                                              | —        | Injected when the parameter type permits `null` and nothing else resolves |
| `CouldNotResolveParameter`                                                                          | —        | Thrown when no resolver succeeds and no fallback exists                   |

**Circular dependency detection** — `ObjectInstantiator` tracks the current instantiation stack and throws `CircularDependencyFound` if a class is encountered twice. Define `DEBUG_CIRCULAR_DEPENDENCIES` to enable enhanced detection that records the source file and line where each dependency was first requested.

The package registers `PreferredDefaultFinder` and `EnvValueResolver` during `initialize()`. `ServiceFinderByType` is registered inside `ParameterResolveManager` unconditionally.

## Usage

### Package developer context

Register the package:

```php
use Medas\ObjectInstantiator\ObjectInstantiatorPackage;

ObjectInstantiatorPackage::instance();
```

**Instantiating a class with automatic DI:**

```php
use Medas\ObjectInstantiator\ObjectInstantiator;
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

```php
use Medas\Core\Interfaces\ParameterResolver;
use Medas\Core\ParameterResolverResult;
use Medas\Core\Attributes\Service;

#[Service]
readonly class CurrentUserResolver implements ParameterResolver
{
    public function __construct(
        private AuthService $authService,
    ) {}

    public function priority(): int
    {
        // Run before ServiceFinderByType (−200)
        return -150;
    }

    public function handle(\ReflectionParameter|\ReflectionProperty $parameter): ParameterResolverResult
    {
        $types = parameterTypes($parameter);

        foreach ($types as $type) {
            if ($type->getName() === User::class) {
                return new ParameterResolverResult(true, $this->authService->currentUser());
            }
        }

        return new ParameterResolverResult(false);
    }
}
```

Register it in your `ServiceConfig`:

```php
$config->addParameterResolver(service(CurrentUserResolver::class));
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
define('DEBUG_CIRCULAR_DEPENDENCIES', true);
```

With this constant defined, `DebuggedCircularDependencyFound` is thrown instead of `CircularDependencyFound`, and it includes the file and line where each class in the cycle was first requested.

### Backend user context

Object instantiation and DI are fully automatic once packages are registered — no manual wiring is needed for classes annotated with `#[Service]`. The instantiator is most commonly used directly only in two situations:

**Testing** — instantiate a class with specific mock arguments while letting everything else be resolved automatically:

```php
$service = $instantiator->instantiate(InvoiceService::class, [
    'repository' => $mockRepository,
]);
```

**Dynamic class creation** — when the class name is only known at runtime (e.g., from a config value or command-line argument):

```php
$handlerClass = $config->getValue('payment.handler-class');
$handler = $instantiator->instantiate($handlerClass);
```
