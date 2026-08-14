<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Exceptions;

class ProcessDisabledException extends PolyglotException
{
    public static function make(): self
    {
        return new self(
            message: 'Running local processes is disabled. It is arbitrary code execution '
                . 'reachable from configuration, so it requires a deliberate '
                . 'laranail.polyglot.process.enabled = true.',
            code: 3003,
        );
    }
}
