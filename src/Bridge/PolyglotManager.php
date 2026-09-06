<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Bridge;

use Throwable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Contracts\Events\Dispatcher;
use Simtabi\Laranail\Polyglot\Enums\ErrorCode;
use Simtabi\Laranail\Polyglot\Enums\Transport;
use Simtabi\Laranail\Polyglot\Enums\TaskStatus;
use Simtabi\Laranail\Polyglot\Tasks\TaskHandle;
use Simtabi\Laranail\Polyglot\Events\CallFailed;
use Simtabi\Laranail\Polyglot\Http\HealthReport;
use Simtabi\Laranail\Polyglot\ValueObjects\Call;
use Simtabi\Laranail\Polyglot\Events\CallStarted;
use Simtabi\Laranail\Polyglot\Contracts\TaskStore;
use Simtabi\Laranail\Polyglot\Contracts\HttpClient;
use Simtabi\Laranail\Polyglot\Events\CallSucceeded;
use Simtabi\Laranail\Polyglot\Events\TaskSubmitted;
use Simtabi\Laranail\Polyglot\Testing\PolyglotFake;
use Simtabi\Laranail\Polyglot\Support\PolyglotConfig;
use Simtabi\Laranail\Polyglot\Contracts\ProcessRunner;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;
use Simtabi\Laranail\Polyglot\ValueObjects\ProcessCall;
use Simtabi\Laranail\Polyglot\Exceptions\PolyglotException;
use Simtabi\Laranail\Polyglot\Bridge\Transports\HttpTransport;
use Simtabi\Laranail\Polyglot\Exceptions\ProcessFailedException;
use Simtabi\Laranail\Polyglot\Exceptions\InvalidPayloadException;
use Simtabi\Laranail\Polyglot\Exceptions\UnknownServiceException;
use Simtabi\Laranail\Polyglot\Exceptions\ProcessDisabledException;

/**
 * The facade target: routes a call to the transport that can serve it, and
 * keeps both wide seams reachable.
 *
 * A target naming a registered script goes to the process runner; anything else
 * is an HTTP service. That ordering is deliberate — script names are an
 * explicit allow-list, so it is the narrower set and cannot be widened by a
 * typo in a service name.
 */
class PolyglotManager
{
    private ?PolyglotFake $fake = null;

    public function __construct(
        private readonly PolyglotConfig $config,
        private readonly HttpClient $http,
        private readonly ProcessRunner $process,
        private readonly HttpTransport $httpTransport,
        private readonly TaskStore $tasks,
        private readonly Dispatcher $events,
    ) {}

    // --- Wide seams ---------------------------------------------------------

    public function http(): HttpClient
    {
        return $this->http;
    }

    public function process(): ProcessRunner
    {
        return $this->process;
    }

    // --- HTTP conveniences --------------------------------------------------

    public function service(string $name): PendingRequest
    {
        return $this->http->service($name);
    }

    public function health(string $name): bool
    {
        return $this->fake?->isFaked() === true || $this->http->health($name);
    }

    /**
     * @return array<string, HealthReport>
     */
    public function healthAll(): array
    {
        if ($this->fake instanceof PolyglotFake) {
            return $this->fake->healthAll($this->http->names());
        }

        return $this->http->healthAll();
    }

    // --- The narrow contract ------------------------------------------------

    /**
     * Run a target with a payload, whichever transport serves it.
     *
     * @param array<array-key, mixed> $payload
     */
    public function run(string $target, array $payload = [], ?int $timeout = null): CallResult
    {
        return $this->call(new Call(
            target: $target,
            payload: $payload,
            timeout: $timeout,
        ));
    }

    public function call(Call $call): CallResult
    {
        $started = microtime(true);

        $this->events->dispatch(new CallStarted($call));

        try {
            $result = $this->dispatchCall($call);
        } catch (PolyglotException $e) {
            $result = CallResult::failure(
                $this->errorFor($e),
                $e->getMessage(),
                $this->transportFor($call),
            );
        } catch (Throwable $e) {
            $result = CallResult::failure(
                ErrorCode::Unreachable,
                $e->getMessage(),
                $this->transportFor($call),
            );
        }

        $result = $result
            ->withDuration((microtime(true) - $started) * 1000)
            ->withCorrelationId($call->correlationId);

        $this->events->dispatch($result->ok
            ? new CallSucceeded($call, $result)
            : new CallFailed($call, $result));

        return $result;
    }

    // --- Async tasks --------------------------------------------------------

    /**
     * Hand work over and stop waiting for it.
     *
     * Inference is slow; a synchronous HTTP call to something that takes four
     * minutes is the wrong shape whatever the timeout says. The service answers
     * with a task id, and reports completion either by being polled or by
     * calling back.
     *
     * @param array<array-key, mixed> $payload
     */
    public function submit(string $target, array $payload = [], ?string $callbackUrl = null): TaskHandle
    {
        $result = $this->call(new Call(
            target: $target,
            payload: $callbackUrl === null ? $payload : [...$payload, 'callback_url' => $callbackUrl],
            endpoint: '/submit',
        ));

        $id = $result->get('task_id');

        $handle = new TaskHandle(
            id: is_scalar($id) ? (string) $id : bin2hex(random_bytes(8)),
            target: $target,
            status: $result->ok ? TaskStatus::Running : TaskStatus::Failed,
            pollUrl: $this->pollUrlFor(is_scalar($id) ? (string) $id : ''),
            submittedAt: time(),
        );

        $this->tasks->put($handle, $result->ok ? null : $result);
        $this->events->dispatch(new TaskSubmitted($handle));

        return $handle;
    }

    /**
     * Refresh a handle.
     *
     * A callback may already have written the outcome, so the store is checked
     * first — polling a service that has since called back would otherwise
     * report stale state.
     */
    public function status(TaskHandle $handle): TaskHandle
    {
        $stored = $this->tasks->find($handle->id);

        if ($stored?->isFinished() === true) {
            return $stored;
        }

        $result = $this->call(new Call(
            target: $handle->target,
            method: 'GET',
            endpoint: $this->pollPathFor($handle->id),
        ));

        $raw = $result->get($this->config->string('tasks.status_key', 'status'));

        $status = TaskStatus::tryFrom(is_scalar($raw) ? (string) $raw : '')
            ?? ($stored instanceof TaskHandle ? $stored->status : TaskStatus::Running);

        $refreshed = $handle->withStatus($status);

        $this->tasks->put(
            $refreshed,
            $status->isFinished() ? $result : null,
        );

        return $refreshed;
    }

    /** The result of a finished task, when there is one. */
    public function result(TaskHandle $handle): ?CallResult
    {
        return $this->tasks->result($handle->id);
    }

    // --- Testing ------------------------------------------------------------

    /**
     * Install a fake for both transports.
     *
     * @param array<string, mixed>|callable(Call): CallResult $responses
     */
    public function fake(array|callable $responses = []): PolyglotFake
    {
        return $this->fake = new PolyglotFake($responses);
    }

    public function isFaked(): bool
    {
        return $this->fake instanceof PolyglotFake;
    }

    public function assertSent(?callable $matching = null): static
    {
        $this->requireFake()->assertSent($matching);

        return $this;
    }

    public function assertSentTo(string $target): static
    {
        $this->requireFake()->assertSentTo($target);

        return $this;
    }

    public function assertSentTimes(string $target, int $expected): static
    {
        $this->requireFake()->assertSentTimes($target, $expected);

        return $this;
    }

    public function assertNothingSent(): static
    {
        $this->requireFake()->assertNothingSent();

        return $this;
    }

    /** Drop memoised transport state — Octane boundaries and tests. */
    public function forgetTransports(): void
    {
        $this->fake = null;
    }

    // --- Internals ----------------------------------------------------------

    private function dispatchCall(Call $call): CallResult
    {
        if ($this->fake instanceof PolyglotFake) {
            return $this->fake->handle($call);
        }

        if ($this->isScript($call->target)) {
            return $this->process->run(new ProcessCall(
                script: $call->target,
                payload: $call->payload,
                args: $call->args,
                timeout: $call->timeout,
            ));
        }

        return $this->httpTransport->call($call);
    }

    private function isScript(string $target): bool
    {
        return array_key_exists($target, $this->config->array('process.scripts'));
    }

    private function transportFor(Call $call): Transport
    {
        if ($this->fake instanceof PolyglotFake) {
            return Transport::Fake;
        }

        return $this->isScript($call->target) ? Transport::Process : Transport::Http;
    }

    private function pollPathFor(string $id): string
    {
        return str_replace('{id}', rawurlencode($id), $this->config->string('tasks.poll_path', '/tasks/{id}'));
    }

    private function pollUrlFor(string $id): ?string
    {
        return $id === '' ? null : $this->pollPathFor($id);
    }

    private function errorFor(PolyglotException $e): ErrorCode
    {
        return match (true) {
            $e instanceof UnknownServiceException  => ErrorCode::UnknownService,
            $e instanceof ProcessDisabledException => ErrorCode::Disabled,
            $e instanceof ProcessFailedException   => ErrorCode::ProcessFailed,
            $e instanceof InvalidPayloadException  => ErrorCode::InvalidPayload,
            default                                => ErrorCode::Unreachable,
        };
    }

    private function requireFake(): PolyglotFake
    {
        if (! $this->fake instanceof PolyglotFake) {
            throw new PolyglotException(
                'No fake is installed. Call Polyglot::fake() before asserting.',
                code: 3600,
            );
        }

        return $this->fake;
    }
}
