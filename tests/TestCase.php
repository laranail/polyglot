<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Simtabi\Laranail\Polyglot\Facades\Polyglot;
use Simtabi\Laranail\Polyglot\Providers\PolyglotServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PolyglotServiceProvider::class];
    }

    /**
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['Python' => Polyglot::class];
    }
}
