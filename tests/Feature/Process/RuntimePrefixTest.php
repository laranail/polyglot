<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Tests\Feature\Process;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Polyglot\Contracts\RuntimeResolver;
use Simtabi\Laranail\Polyglot\Exceptions\RuntimeNotFoundException;
use Simtabi\Laranail\Polyglot\Tests\TestCase;
use Simtabi\Laranail\Polyglot\ValueObjects\ResolvedCommand;

/**
 * The structural change that made this package polyglot rather than Python's.
 *
 * A runtime used to be `string $interpreter`, verified as an absolute path that
 * `is_file()` and `is_executable()`. That is exactly right for
 * `/usr/bin/python3` and cannot express the two shapes a general bridge needs:
 * a container invocation is several words, and a compiled binary has no
 * interpreter at all.
 */
final class RuntimePrefixTest extends TestCase
{
    // -----------------------------------------------------------------
    // The three shapes
    // -----------------------------------------------------------------

    #[Test]
    public function a_single_path_still_resolves_as_it_always_did(): void
    {
        config()->set('laranail.polyglot.process.runtimes', ['default' => PHP_BINARY]);

        self::assertSame([PHP_BINARY], $this->resolver()->resolve());
    }

    #[Test]
    public function a_list_expresses_a_container_invocation(): void
    {
        // The case that could not be written before: the "interpreter" is four
        // words, and only the first of them is a command.
        config()->set('laranail.polyglot.process.runtimes', [
            'docker' => ['docker', 'run', '--rm', 'my-image'],
        ]);

        self::assertSame(['docker', 'run', '--rm', 'my-image'], $this->resolver()->resolve('docker'));
    }

    #[Test]
    public function an_empty_list_means_the_target_runs_itself(): void
    {
        // A compiled binary. Distinct from an unconfigured runtime, which
        // falls back to the conventional virtualenv.
        config()->set('laranail.polyglot.process.runtimes', ['binary' => []]);

        self::assertSame([], $this->resolver()->resolve('binary'));
    }

    // -----------------------------------------------------------------
    // What is still checked, and what deliberately is not
    // -----------------------------------------------------------------

    #[Test]
    public function a_single_bare_name_is_still_refused(): void
    {
        // $PATH decides what `python3` means, and the difference between the
        // system interpreter and a project virtualenv is every dependency the
        // script needs.
        config()->set('laranail.polyglot.process.runtimes', ['default' => 'python3']);

        $this->expectException(RuntimeNotFoundException::class);
        $this->resolver()->resolve();
    }

    #[Test]
    public function a_single_path_that_is_not_executable_is_still_refused(): void
    {
        config()->set('laranail.polyglot.process.runtimes', ['default' => '/definitely/not/here']);

        $this->expectException(RuntimeNotFoundException::class);
        $this->resolver()->resolve();
    }

    #[Test]
    public function a_multi_word_prefix_is_not_path_checked(): void
    {
        // Deliberate, not an oversight. `docker` is meant to come from $PATH —
        // requiring /usr/bin/docker would break on every host that installs it
        // elsewhere — and the words after it are arguments, not files. The
        // command is still never passed through a shell.
        config()->set('laranail.polyglot.process.runtimes', [
            'docker' => ['docker', 'run', '--rm', '--network=none', 'img'],
        ]);

        self::assertCount(5, $this->resolver()->resolve('docker'));
    }

    #[Test]
    public function an_unknown_runtime_names_itself(): void
    {
        config()->set('laranail.polyglot.process.runtimes', ['default' => PHP_BINARY]);

        $this->expectException(RuntimeNotFoundException::class);
        $this->expectExceptionMessage('nope');

        $this->resolver()->resolve('nope');
    }

    // -----------------------------------------------------------------
    // ResolvedCommand
    // -----------------------------------------------------------------

    #[Test]
    public function it_builds_the_full_argv_as_an_array(): void
    {
        $command = new ResolvedCommand(
            name: 'embed',
            path: '/app/scripts/embed.py',
            prefix: ['/usr/bin/python3'],
        );

        self::assertSame(
            ['/usr/bin/python3', '/app/scripts/embed.py', '--fast'],
            $command->toCommand(['--fast']),
        );
    }

    #[Test]
    public function a_container_prefix_produces_a_container_command(): void
    {
        $command = new ResolvedCommand(
            name: 'embed',
            path: '/work/embed.py',
            prefix: ['docker', 'run', '--rm', 'my-image'],
        );

        self::assertSame(
            ['docker', 'run', '--rm', 'my-image', '/work/embed.py'],
            $command->toCommand(),
        );
    }

    #[Test]
    public function a_binary_is_its_own_command(): void
    {
        $command = new ResolvedCommand(name: 'tool', path: '/usr/local/bin/tool', prefix: []);

        self::assertSame(['/usr/local/bin/tool'], $command->toCommand());
        self::assertTrue($command->isDirectlyExecutable());
    }

    #[Test]
    public function an_argument_containing_a_semicolon_stays_one_argument(): void
    {
        // The array discipline, restated at the new seam: nothing here is
        // quoted because nothing is ever concatenated into a shell string.
        $command = new ResolvedCommand(name: 'x', path: '/app/x.py', prefix: ['/usr/bin/python3']);

        $argv = $command->toCommand(['; rm -rf /']);

        self::assertCount(3, $argv);
        self::assertSame('; rm -rf /', $argv[2]);
    }

    private function resolver(): RuntimeResolver
    {
        return $this->app->make(RuntimeResolver::class);
    }
}
