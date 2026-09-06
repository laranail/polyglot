<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Tests\Feature;

use Simtabi\Laranail\Polyglot\Tests\TestCase;
use Simtabi\Laranail\Polyglot\Enums\Transport;
use Simtabi\Laranail\Polyglot\Facades\Polyglot;
use Simtabi\Laranail\Polyglot\ValueObjects\Call;
use Simtabi\Laranail\Polyglot\Testing\RecordedCall;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;
use Simtabi\Laranail\Polyglot\Exceptions\PolyglotException;

final class TestingHelpersTest extends TestCase
{
    public function test_a_bare_fake_succeeds_with_nothing(): void
    {
        Polyglot::fake();

        $result = Polyglot::run('fastapi:predict', ['x' => 1]);

        self::assertTrue($result->ok);
        self::assertSame([], $result->data);
    }

    public function test_a_keyed_fake_answers_by_target(): void
    {
        Polyglot::fake(['embed' => ['vector' => [0.1, 0.2]]]);

        self::assertSame([0.1, 0.2], Polyglot::run('embed')->get('vector'));
    }

    public function test_a_glob_stands_in_for_a_whole_service(): void
    {
        Polyglot::fake(['fastapi:*' => ['served' => true]]);

        self::assertTrue(Polyglot::run('fastapi:predict')->get('served'));
        self::assertTrue(Polyglot::run('fastapi:embed')->get('served'));
    }

    public function test_a_callable_fake_sees_the_call(): void
    {
        Polyglot::fake(fn (Call $call): CallResult => CallResult::ok(['echo' => $call->target]));

        self::assertSame('anything', Polyglot::run('anything')->get('echo'));
    }

    public function test_a_fake_reports_itself_rather_than_pretending_to_be_healthy(): void
    {
        // A fake left installed on a deployed path should be loud, not green.
        Polyglot::fake();

        $result = Polyglot::run('fastapi');

        self::assertSame(Transport::Fake, $result->via);
        self::assertSame('faked', Polyglot::healthAll()['fastapi']->via->label());
    }

    public function test_it_asserts_what_was_sent(): void
    {
        Polyglot::fake();

        Polyglot::run('embed', ['text' => 'hello']);
        Polyglot::run('embed', ['text' => 'again']);

        Polyglot::assertSent();
        Polyglot::assertSentTo('embed');
        Polyglot::assertSentTimes('embed', 2);
        Polyglot::assertSent(fn (RecordedCall $c): bool => $c->call->payload['text'] === 'hello');
    }

    public function test_it_asserts_nothing_was_sent(): void
    {
        Polyglot::fake();

        Polyglot::assertNothingSent();
    }

    public function test_asserting_without_a_fake_is_an_error_rather_than_a_pass(): void
    {
        $this->expectException(PolyglotException::class);

        Polyglot::assertNothingSent();
    }

    public function test_forgetting_transports_drops_the_fake(): void
    {
        Polyglot::fake();
        self::assertTrue(Polyglot::isFaked());

        Polyglot::forgetTransports();

        self::assertFalse(Polyglot::isFaked());
    }
}
