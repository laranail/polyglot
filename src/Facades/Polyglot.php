<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Facades;

use Illuminate\Support\Facades\Facade;
use Illuminate\Http\Client\PendingRequest;
use Simtabi\Laranail\Polyglot\Http\HealthReport;
use Simtabi\Laranail\Polyglot\ValueObjects\Call;
use Simtabi\Laranail\Polyglot\Contracts\HttpClient;
use Simtabi\Laranail\Polyglot\Testing\PolyglotFake;
use Simtabi\Laranail\Polyglot\Bridge\PolyglotManager;
use Simtabi\Laranail\Polyglot\Contracts\ProcessRunner;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;

/**
 * @method static HttpClient http()
 * @method static ProcessRunner process()
 * @method static PendingRequest service(string $name)
 * @method static bool health(string $name)
 * @method static array<string, HealthReport> healthAll()
 * @method static CallResult run(string $target, array $payload = [], ?int $timeout = null)
 * @method static CallResult call(Call $call)
 * @method static PolyglotFake fake(array|callable $responses = [])
 * @method static bool isFaked()
 * @method static PolyglotManager assertSent(?callable $matching = null)
 * @method static PolyglotManager assertSentTo(string $target)
 * @method static PolyglotManager assertSentTimes(string $target, int $expected)
 * @method static PolyglotManager assertNothingSent()
 * @method static void forgetTransports()
 *
 * @see PolyglotManager
 */
final class Polyglot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PolyglotManager::class;
    }
}
