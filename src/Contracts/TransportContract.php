<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Contracts;

use Simtabi\Laranail\Polyglot\ValueObjects\Call;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;

/**
 * The narrow seam: what HTTP and a local process can both honestly promise.
 *
 * Deliberately small. Everything either transport does well — a fluent
 * PendingRequest on one side, stdin and an output callback on the other — stays
 * on its own wide contract, because a shape that fits both would have neither.
 */
interface TransportContract
{
    public function call(Call $call): CallResult;

    /** Whether this transport is the one that serves a given target. */
    public function supports(string $target): bool;
}
