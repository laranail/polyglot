<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Contracts;

use Simtabi\Laranail\Polyglot\Exceptions\ScriptNotAllowedException;
use Simtabi\Laranail\Polyglot\ValueObjects\ResolvedCommand;

/**
 * Turns a caller's script reference into something safe to execute, or refuses.
 *
 * There is no "best guess" here. Every failure is a refusal.
 */
interface ScriptResolver
{
    /**
     * @throws ScriptNotAllowedException
     */
    public function resolve(string $script): ResolvedCommand;

    /** @return list<string> */
    public function names(): array;
}
