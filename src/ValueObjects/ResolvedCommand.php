<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\ValueObjects;

/**
 * A target that has passed every check and is safe to hand to the runner.
 *
 * Only a resolver constructs one. That is the point: the runner's signature
 * takes this type rather than a string, so there is no path that executes
 * something nobody validated.
 *
 * ## Why the prefix is a list and not a string
 *
 * It used to be `string $interpreter`, and the resolver insisted the value was
 * an absolute path that `is_file()` and `is_executable()`. That is exactly
 * right for `/usr/bin/python3` and cannot express the two other shapes this
 * package now has to support:
 *
 * ```php
 * ['docker', 'run', '--rm', 'my-image']   // a container — the "interpreter" is four words
 * ['/usr/bin/python3']                    // an interpreter, as before
 * []                                      // a compiled binary; the script IS the executable
 * ```
 *
 * A list also keeps the array-command discipline the runner already relies on:
 * the whole command line is built as an array and never passed through a shell,
 * so nothing here needs quoting and nothing can be turned into a second command
 * by an argument that contains a semicolon.
 */
final readonly class ResolvedCommand
{
    /**
     * @param list<string> $prefix the words before the target — an interpreter,
     *                             a container invocation, or nothing at all
     * @param array<string, string> $env
     */
    public function __construct(
        public string $name,
        public string $path,
        public array $prefix,
        public ?int $timeout = null,
        public bool $allowsFlags = false,
        public array $env = [],
    ) {}

    /**
     * The full argv, ready for `Process`.
     *
     * @param list<string> $arguments
     * @return list<string>
     */
    public function toCommand(array $arguments = []): array
    {
        return [...$this->prefix, $this->path, ...$arguments];
    }

    /**
     * Whether the target is executed directly rather than by something else.
     *
     * True for a compiled binary. `doctor` reports these differently because
     * there is no interpreter to ask for a version.
     */
    public function isDirectlyExecutable(): bool
    {
        return $this->prefix === [];
    }

    /**
     * A readable form for diagnostics. Never used to execute anything — the
     * runner takes {@see toCommand()}, which stays an array.
     */
    public function describe(): string
    {
        return implode(' ', $this->toCommand());
    }
}
