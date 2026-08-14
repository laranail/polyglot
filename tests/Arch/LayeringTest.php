<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;

arch('the package never reaches a shell')
    ->expect(['shell_exec', 'exec', 'passthru', 'system', 'proc_open', 'popen'])
    ->not->toBeUsed();

arch('nothing is left debugging')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('env is read in the config file and nowhere else')
    ->expect('Simtabi\Laranail\Polyglot')
    ->not->toUse('env');

arch('contracts are interfaces')
    ->expect('Simtabi\Laranail\Polyglot\Contracts')
    ->toBeInterfaces();

arch('enums are string backed')
    ->expect('Simtabi\Laranail\Polyglot\Enums')
    ->toBeStringBackedEnums();

arch('value objects stay immutable')
    ->expect('Simtabi\Laranail\Polyglot\ValueObjects')
    ->toBeReadonly();

arch('phpunit stays out of production code')
    ->expect('Simtabi\Laranail\Polyglot')
    ->not->toUse(Assert::class)
    ->ignoring('Simtabi\Laranail\Polyglot\Testing');

arch('transports and resolvers take their dependencies by injection')
    ->expect([
        'Simtabi\Laranail\Polyglot\Http',
        'Simtabi\Laranail\Polyglot\Process',
        'Simtabi\Laranail\Polyglot\Bridge',
    ])
    ->not->toUse(['app', 'config', 'request', 'session', 'resolve']);

arch('strict types everywhere')
    ->expect('Simtabi\Laranail\Polyglot')
    ->toUseStrictTypes();
