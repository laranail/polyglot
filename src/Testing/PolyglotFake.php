<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Testing;

use PHPUnit\Framework\Assert;
use Simtabi\Laranail\Polyglot\Enums\Transport;
use Simtabi\Laranail\Polyglot\Http\HealthReport;
use Simtabi\Laranail\Polyglot\ValueObjects\Call;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;

/**
 * Stands in for both transports and records what was asked of them.
 *
 * ## Two properties worth keeping
 *
 * It reports {@see Transport::Fake}, so `doctor` and `healthAll()` print
 * "faked" rather than "healthy". A fake left installed on a deployed path
 * should be loud, not silently green.
 *
 * And `Polyglot::service()` still hands back a real `PendingRequest` under a
 * fake. `Http::fake()` remains the right tool when the assertion is about the
 * wire — a URL, a header, a request body. This one is for asserting about the
 * *call*: which target, what payload. The two compose; neither replaces the
 * other.
 */
final class PolyglotFake
{
    /** @var list<RecordedCall> */
    private array $recorded = [];

    /**
     * @param array<string, mixed>|callable(Call): CallResult $responses
     */
    public function __construct(private $responses = []) {}

    public function handle(Call $call): CallResult
    {
        $result = $this->resolve($call)->withTransport(Transport::Fake);

        $this->recorded[] = new RecordedCall($call, $result);

        return $result;
    }

    public function isFaked(): bool
    {
        return true;
    }

    /**
     * @param list<string> $names
     *
     * @return array<string, HealthReport>
     */
    public function healthAll(array $names): array
    {
        $reports = [];

        foreach ($names as $name) {
            $reports[$name] = new HealthReport(
                name: $name,
                healthy: true,
                baseUrl: 'faked',
                tlsMode: 'faked',
                via: Transport::Fake,
            );
        }

        return $reports;
    }

    /** @return list<RecordedCall> */
    public function recorded(): array
    {
        return $this->recorded;
    }

    // --- Assertions ---------------------------------------------------------

    public function assertSent(?callable $matching = null): void
    {
        if ($matching === null) {
            Assert::assertNotEmpty($this->recorded, 'No polyglot calls were made.');

            return;
        }

        $matched = array_filter($this->recorded, static fn (RecordedCall $c): bool => (bool) $matching($c));

        Assert::assertNotEmpty($matched, 'No polyglot call matched the given predicate.');
    }

    public function assertSentTo(string $target): void
    {
        Assert::assertContains(
            $target,
            array_map(static fn (RecordedCall $c): string => $c->call->target, $this->recorded),
            "No polyglot call was sent to [{$target}].",
        );
    }

    public function assertSentTimes(string $target, int $expected): void
    {
        $actual = count(array_filter(
            $this->recorded,
            static fn (RecordedCall $c): bool => $c->call->target === $target,
        ));

        Assert::assertSame(
            $expected,
            $actual,
            "Expected [{$target}] to be called {$expected} time(s), got {$actual}.",
        );
    }

    public function assertNothingSent(): void
    {
        Assert::assertEmpty(
            $this->recorded,
            'Expected no polyglot calls, got: ' . implode(', ', array_map(
                static fn (RecordedCall $c): string => $c->call->target,
                $this->recorded,
            )),
        );
    }

    // --- Internals ----------------------------------------------------------

    private function resolve(Call $call): CallResult
    {
        if (is_callable($this->responses)) {
            return ($this->responses)($call);
        }

        foreach ($this->responses as $pattern => $response) {
            if ($this->matches($pattern, $call->target)) {
                return $response instanceof CallResult
                    ? $response
                    : CallResult::ok(is_array($response) ? $response : ['value' => $response]);
            }
        }

        return CallResult::ok();
    }

    /**
     * Exact match, or a glob so `'fastapi:*'` can stand in for a whole service.
     */
    private function matches(string $pattern, string $target): bool
    {
        return $pattern === $target || fnmatch($pattern, $target);
    }
}
