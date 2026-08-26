<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Providers;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\Facades\Route;
use Override;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Polyglot\Bridge\PolyglotManager;
use Simtabi\Laranail\Polyglot\Bridge\Transports\HttpTransport;
use Simtabi\Laranail\Polyglot\Callbacks\CacheReplayGuard;
use Simtabi\Laranail\Polyglot\Callbacks\HmacCallbackVerifier;
use Simtabi\Laranail\Polyglot\Commands\DoctorCommand;
use Simtabi\Laranail\Polyglot\Commands\HealthCommand;
use Simtabi\Laranail\Polyglot\Commands\InstallCommand;
use Simtabi\Laranail\Polyglot\Commands\MakeServiceCommand;
use Simtabi\Laranail\Polyglot\Commands\RunCommand;
use Simtabi\Laranail\Polyglot\Contracts\CallbackVerifier;
use Simtabi\Laranail\Polyglot\Contracts\HttpClient;
use Simtabi\Laranail\Polyglot\Contracts\ProcessRunner;
use Simtabi\Laranail\Polyglot\Contracts\ReplayGuard;
use Simtabi\Laranail\Polyglot\Contracts\RuntimeResolver;
use Simtabi\Laranail\Polyglot\Contracts\ScriptResolver;
use Simtabi\Laranail\Polyglot\Contracts\TaskStore;
use Simtabi\Laranail\Polyglot\Http\HttpClientService;
use Simtabi\Laranail\Polyglot\Http\Middleware\VerifySignature;
use Simtabi\Laranail\Polyglot\Process\ProcessRunnerService;
use Simtabi\Laranail\Polyglot\Process\Resolvers\AllowListScriptResolver;
use Simtabi\Laranail\Polyglot\Process\Resolvers\ConfiguredRuntimeResolver;
use Simtabi\Laranail\Polyglot\Process\Resolvers\RootClampScriptResolver;
use Simtabi\Laranail\Polyglot\Support\PolyglotConfig;
use Simtabi\Laranail\Polyglot\Support\SystemClock;
use Simtabi\Laranail\Polyglot\Tasks\CacheTaskStore;

final class PolyglotServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/polyglot')
            ->setPublishTagId('polyglot')
            ->hasConfigFile('polyglot')
            ->hasCommands([
                DoctorCommand::class,
                HealthCommand::class,
                RunCommand::class,
                InstallCommand::class,
                MakeServiceCommand::class,
            ]);
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->app->singleton(
            PolyglotConfig::class,
            static fn (Application $app): PolyglotConfig => new PolyglotConfig($app->make(ConfigRepository::class)),
        );

        $this->app->singleton(ClockInterface::class, SystemClock::class);

        $this->app->singleton(
            RuntimeResolver::class,
            fn (Application $app): RuntimeResolver => new ConfiguredRuntimeResolver(
                $app->make(PolyglotConfig::class),
                $this->processRoot($app->make(PolyglotConfig::class)),
            ),
        );

        // Which resolver is installed IS the security posture, so it is decided
        // once here rather than branched on at every call.
        $this->app->singleton(ScriptResolver::class, function (Application $app): ScriptResolver {
            $config = $app->make(PolyglotConfig::class);
            $root = $this->processRoot($config);

            return $config->bool('process.allow_arbitrary_paths', false)
                ? new RootClampScriptResolver($config, $app->make(RuntimeResolver::class), $root)
                : new AllowListScriptResolver($config, $app->make(RuntimeResolver::class), $root);
        });

        $this->app->singleton(
            HttpClient::class,
            static fn (Application $app): HttpClient => new HttpClientService(
                $app->make(PolyglotConfig::class),
                $app->make(HttpFactory::class),
                $app->make(LoggerInterface::class),
            ),
        );

        // Bound even when disabled. A binding that appears and disappears with
        // configuration is one that eventually fails at resolve time in the one
        // path nobody tested; disabled means run() throws, which says so.
        $this->app->singleton(
            ProcessRunner::class,
            static fn (Application $app): ProcessRunner => new ProcessRunnerService(
                $app->make(PolyglotConfig::class),
                $app->make(ScriptResolver::class),
                $app->make(ProcessFactory::class),
                $app->make(LoggerInterface::class),
            ),
        );

        $this->app->singleton(
            HttpTransport::class,
            static fn (Application $app): HttpTransport => new HttpTransport(
                $app->make(HttpClient::class),
                $app->make(PolyglotConfig::class),
            ),
        );

        $this->app->singleton(
            ReplayGuard::class,
            static fn (Application $app): ReplayGuard => new CacheReplayGuard(
                $app->make(CacheFactory::class),
                $app->make(PolyglotConfig::class),
            ),
        );

        $this->app->singleton(
            CallbackVerifier::class,
            static fn (Application $app): CallbackVerifier => new HmacCallbackVerifier(
                $app->make(PolyglotConfig::class),
                $app->make(ReplayGuard::class),
                $app->make(ClockInterface::class),
            ),
        );

        $this->app->singleton(
            TaskStore::class,
            static fn (Application $app): TaskStore => new CacheTaskStore(
                $app->make(CacheFactory::class),
                $app->make(PolyglotConfig::class),
            ),
        );

        $this->app->singleton(
            PolyglotManager::class,
            static fn (Application $app): PolyglotManager => new PolyglotManager(
                $app->make(PolyglotConfig::class),
                $app->make(HttpClient::class),
                $app->make(ProcessRunner::class),
                $app->make(HttpTransport::class),
                $app->make(TaskStore::class),
                $app->make(Dispatcher::class),
            ),
        );

        $this->app->alias(PolyglotManager::class, 'polyglot');
    }

    #[Override]
    public function packageBooted(): void
    {
        $this->registerCallbackRoutes();
        $this->registerOctaneReset();
    }

    /**
     * Mount the callback route, but only when it was asked for.
     *
     * An unauthenticated POST endpoint standing in every application that never
     * uses one is a liability with no upside, so the route is not registered at
     * all rather than registered and guarded.
     *
     * Not `hasRoutesWhen()`: the group needs a config-driven prefix, middleware
     * stack AND rate limiter around it, which needs an explicit group.
     */
    private function registerCallbackRoutes(): void
    {
        $config = $this->app->make(PolyglotConfig::class);

        if (! $config->bool('callbacks.enabled', false)) {
            return;
        }

        $middleware = $config->stringList('callbacks.middleware');
        $rateLimit = $config->string('callbacks.rate_limit', '60,1');

        if ($rateLimit !== '') {
            $middleware[] = 'throttle:' . $rateLimit;
        }

        $middleware[] = VerifySignature::class;

        Route::group([
            'prefix' => $config->string('callbacks.prefix', 'api/polyglot'),
            'middleware' => $middleware,
        ], function (): void {
            $this->loadRoutesFrom($this->packagePath('routes/callbacks.php'));
        });
    }

    /**
     * Where scripts live. Defaults to `base_path('polyglot')`, the conventional
     * home for a sidecar in a Laravel repo.
     */
    private function processRoot(PolyglotConfig $config): string
    {
        $configured = $config->stringOrNull('process.root');

        return $configured !== null
            ? rtrim($configured, '/')
            : rtrim($this->app->basePath('polyglot'), '/');
    }

    /**
     * Clear memoised transport state at both Octane boundaries.
     *
     * Listened for by event *name*, so there is no `class_exists` probe and no
     * dependency on Octane being installed. Without this a `Polyglot::fake()` in
     * one request leaks into the next.
     */
    private function registerOctaneReset(): void
    {
        $events = $this->app->make(Dispatcher::class);

        foreach (['Laravel\Octane\Events\RequestReceived', 'Laravel\Octane\Events\RequestTerminated'] as $event) {
            $events->listen($event, function (): void {
                if ($this->app->resolved(PolyglotManager::class)) {
                    $this->app->make(PolyglotManager::class)->forgetTransports();
                }
            });
        }
    }
}
