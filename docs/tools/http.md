# The HTTP transport

Named services resolved from config. Adding one is a config entry, not code.

```php
use Simtabi\Laranail\Polyglot\Facades\Polyglot;

$response = Polyglot::service('fastapi')->post('/embed', ['text' => 'hello']);
```

`service()` hands back a **real** `Illuminate\Http\Client\PendingRequest`, so
`attach()`, `sink()`, streaming and everything else Laravel's client can do
still work. The package configures it; it does not wrap it in something smaller.

## A service entry

```php
'services' => [
    'fastapi' => [
        'base_url'        => env('POLYGLOT_FASTAPI_URL', 'http://127.0.0.1:8000'),
        'timeout'         => env('POLYGLOT_FASTAPI_TIMEOUT'),
        'connect_timeout' => env('POLYGLOT_FASTAPI_CONNECT_TIMEOUT'),
        'verify_ssl'      => env('POLYGLOT_FASTAPI_VERIFY_SSL', true),
        'ca_cert'         => env('POLYGLOT_FASTAPI_CA_CERT'),
        'health_path'     => env('POLYGLOT_FASTAPI_HEALTH_PATH', '/health'),
        'health_key'      => env('POLYGLOT_FASTAPI_HEALTH_KEY', 'status'),
        'healthy_value'   => env('POLYGLOT_FASTAPI_HEALTHY_VALUE', 'healthy'),
        'retry_times'     => env('POLYGLOT_FASTAPI_RETRY_TIMES', 3),
        'retry_sleep_ms'  => env('POLYGLOT_FASTAPI_RETRY_SLEEP_MS', 100),
        'auth'            => ['scheme' => 'none', /* … */],
        'headers'         => [],
    ],
],
```

`fastapi` and `flask` ship as examples. **The names are just names** — nothing
about them is Python. A service called `gin` pointing at a Go binary works
identically, which is the whole argument for the rename.

## Authentication belongs in the config

```php
'auth' => [
    'scheme'   => 'bearer',        // none | bearer | api_key | basic
    'token'    => env('POLYGLOT_FASTAPI_TOKEN'),
    'header'   => 'X-API-Key',     // api_key only
    'username' => …, 'password' => …,   // basic only
],
```

The client this came from had no notion of credentials, so every consumer bolted
its own header on **at the call site** — where the redactor could not see it, and
where a debug log or an exception message printed it verbatim.

Declaring it here means one place knows the secret, and one place can hide it.

## TLS

```php
'verify_ssl' => true,
'ca_cert'    => '/path/to/root.crt',
```

`verify_ssl => false` is refused in production and reported by `doctor`.

**`ca_cert` is the better answer to a self-signed proxy**: it keeps verification
on and trusts one extra root, rather than trusting everything. A set-but-missing
`ca_cert` falls back to the system trust store — never to `false`, because
"the file moved" must not silently become "verify nothing".

## Health

```php
Polyglot::health('fastapi');    // bool
Polyglot::healthAll();          // array<string, HealthReport>
```

A `HealthReport` carries base URL, TLS mode and round-trip time, for readiness
endpoints and CI. The contract is configurable — `health_path`, `health_key`,
`healthy_value` — because "what does healthy look like" is the service's
decision, not this package's.

A **faked** transport reports itself as faked rather than healthy.

## No `fastapi()` or `flask()` methods

They existed and were removed. They were the only place the *interface* named a
language, which made a package that talks to any runtime look like one that
talks to Python and tolerates the rest.

```php
Polyglot::service('fastapi');   // says exactly the same thing
```

## Faking it

```php
Polyglot::fake(['fastapi' => ['vector' => [0.1, 0.2]]]);

// …

Polyglot::assertSentTo('fastapi');
Polyglot::assertSentTimes('fastapi', 1);
Polyglot::assertNothingSent();
```

---
[← Docs index](../../README.md#documentation)
