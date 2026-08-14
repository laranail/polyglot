<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Tests\Feature;

use Simtabi\Laranail\Polyglot\Bridge\PolyglotManager;
use Simtabi\Laranail\Polyglot\Bridge\Transports\HttpTransport;
use Simtabi\Laranail\Polyglot\Contracts\HttpClient;
use Simtabi\Laranail\Polyglot\Contracts\ProcessRunner;
use Simtabi\Laranail\Polyglot\Contracts\RuntimeResolver;
use Simtabi\Laranail\Polyglot\Contracts\ScriptResolver;
use Simtabi\Laranail\Polyglot\Facades\Polyglot;
use Simtabi\Laranail\Polyglot\Process\Resolvers\AllowListScriptResolver;
use Simtabi\Laranail\Polyglot\Process\Resolvers\RootClampScriptResolver;
use Simtabi\Laranail\Polyglot\Support\PolyglotConfig;
use Simtabi\Laranail\Polyglot\Tests\TestCase;

final class BootHealthTest extends TestCase
{
    public function test_every_binding_resolves_after_a_normal_boot(): void
    {
        foreach ([
            PolyglotConfig::class,
            HttpClient::class,
            ProcessRunner::class,
            ScriptResolver::class,
            RuntimeResolver::class,
            HttpTransport::class,
            PolyglotManager::class,
        ] as $abstract) {
            self::assertTrue($this->app->bound($abstract), "{$abstract} is not bound.");
            self::assertNotNull($this->app->make($abstract));
        }
    }

    public function test_the_config_is_merged_under_the_namespaced_key(): void
    {
        self::assertSame('fastapi', config('laranail.polyglot.default'));
        self::assertIsArray(config('laranail.polyglot.services.fastapi'));
    }

    public function test_the_facade_resolves_the_manager(): void
    {
        self::assertInstanceOf(PolyglotManager::class, Polyglot::getFacadeRoot());
    }

    public function test_the_allow_list_resolver_is_the_default(): void
    {
        self::assertInstanceOf(AllowListScriptResolver::class, $this->app->make(ScriptResolver::class));
    }

    public function test_the_root_clamp_resolver_is_opt_in(): void
    {
        config()->set('laranail.polyglot.process.allow_arbitrary_paths', true);
        $this->app->forgetInstance(ScriptResolver::class);

        self::assertInstanceOf(RootClampScriptResolver::class, $this->app->make(ScriptResolver::class));
    }

    public function test_the_process_runner_is_bound_even_while_disabled(): void
    {
        self::assertFalse($this->app->make(ProcessRunner::class)->isEnabled());
        self::assertNotNull($this->app->make(ProcessRunner::class));
    }
}
