<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Contracts;

use Simtabi\Laranail\Polyglot\Exceptions\ProcessDisabledException;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;
use Simtabi\Laranail\Polyglot\ValueObjects\ProcessCall;

/**
 * The wide process seam: run a registered script and read its JSON back.
 *
 * Always bound, even when the transport is disabled — a binding that appears
 * and disappears with configuration is one that eventually fails at resolve
 * time in the one code path nobody exercised. Disabled means `run()` throws
 * {@see ProcessDisabledException}, which says so.
 */
interface ProcessRunner
{
    /**
     * @throws ProcessDisabledException when the transport is disabled
     */
    public function run(ProcessCall $call): CallResult;

    /**
     * Logical names of every registered script.
     *
     * @return list<string>
     */
    public function scripts(): array;

    public function isEnabled(): bool;
}
