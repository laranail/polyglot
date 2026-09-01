<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Contracts;

use Illuminate\Http\Client\PendingRequest;
use Simtabi\Laranail\Polyglot\Exceptions\MissingBaseUrlException;
use Simtabi\Laranail\Polyglot\Exceptions\UnknownServiceException;
use Simtabi\Laranail\Polyglot\Http\HealthReport;
use Simtabi\Laranail\Polyglot\Http\ServiceDefinition;

/**
 * The wide HTTP seam: a configured `PendingRequest` for a named service.
 *
 * This deliberately returns Laravel's own client rather than something wrapped.
 * `->attach()`, `->sink()`, `->withOptions()` and streaming are the reason to
 * reach for HTTP in the first place, and a lowest-common-denominator wrapper
 * that also had to fit a subprocess would throw all of it away.
 */
interface HttpClient
{
    /**
     * @throws UnknownServiceException|MissingBaseUrlException
     */
    public function service(string $name): PendingRequest;

    /** Whether one service answers its configured health contract. */
    public function health(string $name): bool;

    /**
     * Probe every configured service, for a readiness endpoint or CI.
     *
     * @return array<string, HealthReport>
     */
    public function healthAll(): array;

    /** A single service's health with its round trip and context. */
    public function report(string $name): HealthReport;

    /**
     * Names of every configured service.
     *
     * @return list<string>
     */
    public function names(): array;

    /**
     * @throws UnknownServiceException
     */
    public function definition(string $name): ServiceDefinition;
}
