<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Exceptions;

class MissingBaseUrlException extends PolyglotException
{
    public static function for(string $name): self
    {
        return new self(
            message: "The [{$name}] service has no base_url. "
                ."Set laranail.polyglot.services.{$name}.base_url.",
            code: 3002,
            context: ['service' => $name],
        );
    }
}
