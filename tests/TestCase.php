<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Tests;

use Simtabi\Laranail\Polyglot\Facades\Polyglot;
use Simtabi\Laranail\Polyglot\Providers\PolyglotServiceProvider;
use Simtabi\Laranail\Package\Tools\Testing\IsolatedTestCase;

abstract class TestCase extends IsolatedTestCase
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
