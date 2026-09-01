<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Exceptions;

class RuntimeNotFoundException extends PolyglotException
{
    public static function notConfigured(string $name): self
    {
        return new self(
            message: "No runtime is configured as [{$name}]. "
                .'Add one under laranail.polyglot.process.runtimes: a path, a list such as docker run --rm image, or an empty list for a target that runs itself.',
            code: 3201,
            context: ['runtime' => $name],
        );
    }

    public static function notAbsolute(string $path): self
    {
        return new self(
            message: "The runtime [{$path}] is not an absolute path. A bare name would be "
                .'resolved through $PATH, which decides what "python3" means and is not '
                .'something this package will guess at.',
            code: 3202,
            context: ['path' => $path],
        );
    }

    public static function notExecutable(string $path): self
    {
        return new self(
            message: "The runtime [{$path}] does not exist or is not executable.",
            code: 3203,
            context: ['path' => $path],
        );
    }
}
