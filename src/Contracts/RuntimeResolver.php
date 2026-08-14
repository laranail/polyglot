<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Contracts;

use Simtabi\Laranail\Polyglot\Exceptions\RuntimeNotFoundException;

/**
 * Resolves a named runtime to the words that go before the target.
 *
 * Returns a `list<string>`, not a path. A runtime is not always one executable:
 *
 * ```
 * ['/usr/bin/python3']                  an interpreter
 * ['docker', 'run', '--rm', 'my-image'] a container
 * []                                    a compiled binary — the target runs itself
 * ```
 *
 * The signature this replaces returned a `string` and the implementation
 * insisted it be an absolute path that `is_file()` and `is_executable()`.
 * Correct for the first shape and incapable of expressing the other two.
 */
interface RuntimeResolver
{
    /**
     * @return list<string> the words preceding the target; empty for a binary
     *
     * @throws RuntimeNotFoundException
     */
    public function resolve(?string $name = null): array;

    /**
     * Every configured runtime, as `name => prefix`.
     *
     * @return array<string, list<string>>
     */
    public function all(): array;
}
