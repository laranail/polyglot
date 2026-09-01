<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Process\Resolvers;

use Simtabi\Laranail\Polyglot\Contracts\RuntimeResolver;
use Simtabi\Laranail\Polyglot\Exceptions\RuntimeNotFoundException;
use Simtabi\Laranail\Polyglot\Support\PolyglotConfig;

/**
 * Resolves a named runtime to the command prefix that runs a target.
 *
 * ## Three shapes, one resolver
 *
 * ```php
 * 'python'  => '/usr/bin/python3',                        // a string, as before
 * 'docker'  => ['docker', 'run', '--rm', 'my-image'],     // a container
 * 'binary'  => [],                                        // the target runs itself
 * ```
 *
 * A single string keeps its old meaning and its old checks: it must be absolute
 * (unless `process.allow_path_lookup` is on) and it must be an executable file.
 * `$PATH` decides what a bare `python3` means, and the difference between the
 * system interpreter and a project virtualenv is every dependency the script
 * needs — so guessing is refused rather than risked.
 *
 * A **list** is not path-checked, and that is deliberate rather than an
 * oversight: `docker` is looked up on `$PATH` by design, the words after it are
 * arguments and not files, and requiring `/usr/bin/docker` would break on every
 * host that installs it somewhere else. The list is still never passed through
 * a shell — the runner builds an array — so nothing in it can become a second
 * command.
 *
 * With nothing configured the fallback is the conventional virtualenv at
 * `{root}/.venv/bin/python`, which is what a Python project in the host repo
 * almost always has. That convention is Python's; a runtime with a different
 * one names it in config.
 */
final readonly class ConfiguredRuntimeResolver implements RuntimeResolver
{
    public function __construct(
        private PolyglotConfig $config,
        private string $root,
    ) {}

    public function resolve(?string $name = null): array
    {
        $name ??= 'default';
        $configured = $this->all()[$name] ?? null;

        if ($configured === null && $name !== 'default') {
            throw RuntimeNotFoundException::notConfigured($name);
        }

        if ($configured === null) {
            return $this->verifiedPath($this->conventionalVenv());
        }

        // An explicitly empty list means "the target is the executable". Not an
        // error, and not the same as an unconfigured runtime.
        if ($configured === []) {
            return [];
        }

        // A single word is an interpreter path and keeps the old guarantees. A
        // longer list is an invocation whose first word is a command and whose
        // rest are arguments; path-checking those would be checking arguments.
        return count($configured) === 1
            ? $this->verifiedPath($configured[0])
            : $configured;
    }

    public function all(): array
    {
        $out = [];

        foreach ($this->config->array('process.runtimes') as $name => $prefix) {
            if (! is_string($name)) {
                continue;
            }

            if (is_string($prefix) && trim($prefix) !== '') {
                $out[$name] = [trim($prefix)];

                continue;
            }

            if (is_array($prefix)) {
                $words = array_values(array_filter(
                    array_map(static fn (mixed $w): string => is_string($w) ? trim($w) : '', $prefix),
                    static fn (string $w): bool => $w !== '',
                ));

                $out[$name] = $words;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function verifiedPath(string $path): array
    {
        if (! str_starts_with($path, '/') && ! $this->config->bool('process.allow_path_lookup', false)) {
            throw RuntimeNotFoundException::notAbsolute($path);
        }

        if (str_starts_with($path, '/') && (! is_file($path) || ! is_executable($path))) {
            throw RuntimeNotFoundException::notExecutable($path);
        }

        return [$path];
    }

    private function conventionalVenv(): string
    {
        return $this->root.'/.venv/bin/python';
    }
}
