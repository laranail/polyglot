<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Process;

use Closure;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\Exceptions\ProcessTimedOutException as LaravelProcessTimedOut;
use Illuminate\Process\Factory as ProcessFactory;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Polyglot\Contracts\ProcessRunner;
use Simtabi\Laranail\Polyglot\Contracts\ScriptResolver;
use Simtabi\Laranail\Polyglot\Enums\ErrorCode;
use Simtabi\Laranail\Polyglot\Enums\Transport;
use Simtabi\Laranail\Polyglot\Exceptions\ProcessDisabledException;
use Simtabi\Laranail\Polyglot\Exceptions\ScriptNotAllowedException;
use Simtabi\Laranail\Polyglot\Support\Json;
use Simtabi\Laranail\Polyglot\Support\PolyglotConfig;
use Simtabi\Laranail\Polyglot\Support\Redactor;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;
use Simtabi\Laranail\Polyglot\ValueObjects\ProcessCall;
use Simtabi\Laranail\Polyglot\ValueObjects\ResolvedCommand;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOut;

/**
 * Runs a registered script and reads its JSON back.
 *
 * ## Never a shell
 *
 * The command is built as an **array** — `[...$prefix, $path, ...$args]` —
 * which `illuminate/process` passes to `proc_open` without going through
 * `/bin/sh`. A semicolon, a backtick or a `$(…)` in an argument is therefore an
 * inert byte rather than a second command. There is no code path in this
 * package that builds a command string, and an arch test asserts the shell
 * functions are never used.
 *
 * ## The payload goes on stdin
 *
 * Not argv. `/proc/<pid>/cmdline` is world-readable on Linux, so an API key
 * passed as an argument is readable by every local process for as long as the
 * script runs. The cost is that scripts must read stdin, which the shipped
 * scaffold demonstrates.
 *
 * ## The environment is an allow-list
 *
 * `inherit_env` is false by default, so the child gets only what config gives
 * it. A child process does not need `APP_KEY` or `DB_PASSWORD`, and a traceback
 * prints whatever it can reach.
 */
final readonly class ProcessRunnerService implements ProcessRunner
{
    public function __construct(
        private PolyglotConfig $config,
        private ScriptResolver $scripts,
        private ProcessFactory $process,
        private LoggerInterface $logger,
    ) {}

    public function run(ProcessCall $call): CallResult
    {
        if (! $this->isEnabled()) {
            throw ProcessDisabledException::make();
        }

        $script = $this->scripts->resolve($call->script);
        $args = $this->guardArguments($call->args, $script);
        $timeout = $call->timeout ?? $script->timeout ?? $this->config->int('process.timeout', 60);
        $redactor = (new Redactor)->rememberAll(array_values($this->environment($script)));

        $pending = $this->process
            ->newPendingProcess()
            ->timeout(max(1, $timeout))
            ->idleTimeout(max(1, $call->idleTimeout ?? $this->config->int('process.idle_timeout', 30)))
            ->path(dirname($script->path))
            ->env($this->environment($script))
            ->input(Json::encode($call->payload));

        $command = $script->toCommand($args);

        try {
            $result = $call->onOutput instanceof Closure
                ? $pending->run($command, $call->onOutput)
                : $pending->run($command);
        } catch (SymfonyProcessTimedOut|LaravelProcessTimedOut) {
            // A timeout is the expected outcome of a hung script, not an
            // exceptional one — it is the reason the clamp exists. Letting
            // Symfony's exception escape makes every caller wrap run() in a
            // try/catch for a vendor class, and makes the timeout the one
            // failure mode that does not arrive as a CallResult.
            //
            // Both classes, because they are unrelated types and which one
            // surfaces depends on the path taken: Laravel's extends
            // RuntimeException and does not inherit from Symfony's, so catching
            // either alone leaves the other escaping.
            //
            // The message is rebuilt rather than reused: the vendor exception
            // embeds the full command line, which names the runtime and
            // script path verbatim.
            return new CallResult(
                ok: false,
                error: ErrorCode::Timeout,
                message: $redactor->tail(
                    "The [{$script->name}] script exceeded its {$timeout}s timeout.",
                    $this->config->int('process.stderr_max_chars', 2000),
                ),
                via: Transport::Process,
            );
        }

        return $this->interpret($script, $result, $redactor);
    }

    public function scripts(): array
    {
        return $this->scripts->names();
    }

    public function isEnabled(): bool
    {
        return $this->config->bool('process.enabled', false);
    }

    /**
     * Turn a finished process into a result.
     */
    private function interpret(ResolvedCommand $script, ProcessResult $result, Redactor $redactor): CallResult
    {
        $stderr = $result->errorOutput();
        $maxChars = $this->config->int('process.stderr_max_chars', 2000);

        if ($this->config->bool('process.log_stderr', false) && trim($stderr) !== '') {
            $this->logger->debug('Child process stderr', [
                'script' => $script->name,
                'stderr' => $redactor->tail($stderr, $maxChars),
            ]);
        }

        if (! $result->successful()) {
            return new CallResult(
                ok: false,
                error: ErrorCode::ProcessFailed,
                message: $redactor->tail(
                    "The [{$script->name}] script exited {$result->exitCode()}. ".$stderr,
                    $maxChars,
                ),
                exitCode: $result->exitCode(),
                via: Transport::Process,
            );
        }

        $limit = $this->config->int('process.max_output_bytes', 8_388_608);
        $output = $result->output();

        if ($limit > 0 && strlen($output) > $limit) {
            return CallResult::failure(
                ErrorCode::ResponseTooLarge,
                'The script produced '.strlen($output)." bytes, over the {$limit}-byte limit.",
                Transport::Process,
            );
        }

        return new CallResult(
            ok: true,
            data: Json::decode($output, $limit, $this->config->int('defaults.json_depth', Json::MAX_DEPTH)),
            exitCode: $result->exitCode(),
            via: Transport::Process,
        );
    }

    /**
     * Refuse an argument that looks like a flag.
     *
     * An argparse script given `--output=/etc/cron.d/x` writes where the caller
     * said, not where the application meant. A script that genuinely takes
     * flags opts in with `allows_flags`.
     *
     * @param  list<string>  $args
     * @return list<string>
     */
    private function guardArguments(array $args, ResolvedCommand $script): array
    {
        if ($script->allowsFlags) {
            return array_values($args);
        }

        foreach ($args as $argument) {
            if (str_starts_with($argument, '-')) {
                throw ScriptNotAllowedException::flagArgument($argument);
            }
        }

        return array_values($args);
    }

    /**
     * The child's environment: config only, unless inheritance is asked for.
     *
     * @return array<string, string|false>
     */
    private function environment(ResolvedCommand $script): array
    {
        $env = [];

        foreach ($this->config->array('process.env') as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $env[$key] = (string) $value;
            }
        }

        $env = [...$env, ...$script->env];

        if ($this->config->bool('process.inherit_env', false)) {
            return $env;
        }

        // illuminate/process merges over the parent environment, so anything
        // not listed has to be explicitly unset rather than merely omitted.
        foreach (array_keys($_ENV + $_SERVER) as $key) {
            if (is_string($key) && ! array_key_exists($key, $env) && ! $this->isSafeToInherit($key)) {
                $env[$key] = false;
            }
        }

        return $env;
    }

    /**
     * A short list a child process genuinely needs to run at all.
     */
    private function isSafeToInherit(string $key): bool
    {
        return in_array($key, ['PATH', 'LANG', 'LC_ALL', 'TZ', 'HOME', 'TMPDIR', 'SystemRoot'], true);
    }
}
