<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Process\Resolvers;

use Simtabi\Laranail\Polyglot\Contracts\RuntimeResolver;
use Simtabi\Laranail\Polyglot\Contracts\ScriptResolver;
use Simtabi\Laranail\Polyglot\Exceptions\ScriptNotAllowedException;
use Simtabi\Laranail\Polyglot\Support\PolyglotConfig;
use Simtabi\Laranail\Polyglot\ValueObjects\ResolvedCommand;

/**
 * Accepts any path, as long as it resolves inside the configured root.
 *
 * Opt-in via `process.allow_arbitrary_paths`, for applications that genuinely
 * generate or discover scripts. It is strictly weaker than the allow-list: the
 * caller now controls part of what runs, so the root is the only thing standing
 * between a bug and arbitrary execution.
 *
 * `realpath()` does the work. It collapses `..` and follows symlinks, so both
 * `../../etc/passwd` and a symlink planted inside the root pointing outside it
 * fail the same containment check. A `realpath()` of false is a refusal, never
 * a fall-through to the unresolved string.
 */
final readonly class RootClampScriptResolver implements ScriptResolver
{
    public function __construct(
        private PolyglotConfig $config,
        private RuntimeResolver $runtimes,
        private string $root,
    ) {}

    public function resolve(string $script): ResolvedCommand
    {
        if (trim($script) === '' || str_contains($script, "\0")) {
            throw ScriptNotAllowedException::unreadable($script);
        }

        $candidate = str_starts_with($script, '/') ? $script : $this->root.'/'.ltrim($script, '/');

        $real = realpath($candidate);
        $realRoot = realpath($this->root);

        if ($real === false || ! is_file($real)) {
            throw ScriptNotAllowedException::unreadable($candidate);
        }

        if ($realRoot === false || ! str_starts_with($real, $realRoot.DIRECTORY_SEPARATOR)) {
            throw ScriptNotAllowedException::outsideRoot($real, $this->root);
        }

        return new ResolvedCommand(
            name: $script,
            path: $real,
            prefix: $this->runtimes->resolve(),
            timeout: $this->config->int('process.timeout', 60),
        );
    }

    public function names(): array
    {
        return [];
    }
}
