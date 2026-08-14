# Results and errors

Every call returns a `CallResult`. Nothing returns a bare array.

```php
$result = Polyglot::run('embed', ['text' => 'hello']);

$result->failed();                 // bool
$result->get('vector');            // a key from the payload
$result->get('missing', 'default');
$result->has('vector');
$result->throw();                  // raise instead, fluently
$result->toArray();
```

Plus the metadata a call carries whether or not you look at it:

| Member | Answers |
|---|---|
| `errorCode` | Which `ErrorCode`, when it failed |
| `via` | The `Transport` that answered — `Http`, `Process` or `Fake` |
| `durationMs` | Round trip |
| `correlationId` | For matching a log line to a call |

`via` being an enum with a `Fake` case is deliberate: a green health check or a
passing integration test backed by a fake should be *identifiable* as such
rather than indistinguishable from the real thing.

## `ErrorCode`

```php
enum ErrorCode: string {
    case UnknownService    = 'unknown_service';
    case Unreachable       = 'unreachable';
    case HttpError         = 'http_error';
    case Timeout           = 'timeout';
    case ProcessFailed     = 'process_failed';
    case InvalidPayload    = 'invalid_payload';
    case ResponseTooLarge  = 'response_too_large';
    case Disabled          = 'disabled';
}
```

Eight cases rather than a boolean, because the right response differs:

| Code | Means | Usually |
|---|---|---|
| `UnknownService` | Not in the allow-list | A config or typo bug — yours |
| `Disabled` | The transport is switched off | Deliberate; do not alert |
| `Unreachable` | Nothing answered | Retryable |
| `Timeout` | It answered too slowly | Retryable, maybe with a longer clamp |
| `HttpError` | A 4xx or 5xx | Depends which |
| `ProcessFailed` | Non-zero exit | Read stderr — but see below |
| `InvalidPayload` | The response was not the shape promised | The other side changed |
| `ResponseTooLarge` | It exceeded the cap | Stream it instead |

## `Timeout` is a result, not an exception

A hung script is the reason the timeout clamp exists, so a timeout is an
**expected** outcome and arrives the way every other failure does.

It did not always: Symfony's `ProcessTimedOutException` propagated straight out
of `run()`, making the timeout the single failure mode a caller could not handle
uniformly. Both Symfony's and Laravel's timeout classes are now caught — they
are unrelated types, Laravel's extending `RuntimeException`, so catching either
alone left the other escaping.

## Messages are redacted

A failure message never carries:

- **the full command line**, which names the interpreter and script path — the
  vendor timeout exception embeds it, and the rebuilt message drops it;
- **stderr contents verbatim**, which is where a script prints the token it just
  failed to authenticate with;
- **a configured credential**, because auth is declared in config where the
  redactor can see it rather than added at the call site where it cannot.

A test asserts stderr secrets stay out of the message.

## `throw()` when you would rather

```php
$vector = Polyglot::run('embed', $payload)->throw()->get('vector');
```

Fluent, and returns `$this` on success, so it composes into a single expression
when a failure genuinely is exceptional for that call site.

---
[← Docs index](../../README.md#documentation)
