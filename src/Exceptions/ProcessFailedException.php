<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Exceptions;

/**
 * A script ran and did not succeed.
 *
 * `getMessage()` carries only the redacted tail of stderr. {@see rawStderr()}
 * exists for a caller who deliberately opts in, and nothing in this package
 * passes it to a logger — a stack trace embeds locals, and an HTTP client
 * error embeds the full URL, query-string API key included.
 */
class ProcessFailedException extends PolyglotException
{
    private string $rawStderr = '';

    public static function exitedNonZero(string $script, int $exitCode, string $redactedStderr, string $rawStderr): self
    {
        $detail = $redactedStderr === '' ? '' : " {$redactedStderr}";

        $e = new self(
            message: "The [{$script}] script exited {$exitCode}.{$detail}",
            code: 3301,
            context: ['script' => $script, 'exit_code' => $exitCode],
        );

        $e->rawStderr = $rawStderr;

        return $e;
    }

    public static function timedOut(string $script, int $seconds): self
    {
        return new self(
            message: "The [{$script}] script did not finish within {$seconds}s and was terminated.",
            code: 3302,
            context: ['script' => $script, 'timeout' => $seconds],
        );
    }

    public function rawStderr(): string
    {
        return $this->rawStderr;
    }
}
