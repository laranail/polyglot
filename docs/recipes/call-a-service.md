# Call an HTTP service

## Register it

```php
// config/laranail/polyglot.php
'services' => [
    'inference' => [
        'base_url'      => env('POLYGLOT_INFERENCE_URL', 'https://inference.internal'),
        'timeout'       => 10,
        'health_path'   => '/health',
        'auth'          => [
            'scheme' => 'bearer',
            'token'  => env('POLYGLOT_INFERENCE_TOKEN'),
        ],
    ],
],
```

Declare the credential **here**, not at the call site. The client this package
came from had no notion of auth, so every consumer added its own header where
the redactor could not see it — and where an exception message printed it.

## Call it

```php
use Simtabi\Laranail\Polyglot\Facades\Polyglot;

$response = Polyglot::service('inference')->post('/embed', ['text' => $text]);

$vector = $response->json('vector');
```

`service()` returns a real `PendingRequest`, so everything Laravel's HTTP client
does still works:

```php
Polyglot::service('inference')->attach('file', $contents, 'a.wav')->post('/transcribe');
Polyglot::service('inference')->sink($path)->get('/model.bin');
```

## Or go through `run()` for a uniform result

```php
$result = Polyglot::run('inference', ['text' => $text]);

if ($result->failed()) {
    return match ($result->errorCode) {
        ErrorCode::Timeout, ErrorCode::Unreachable => $this->retryLater(),
        ErrorCode::Disabled                        => $this->skip(),
        default                                    => $result->throw(),
    };
}
```

`run()` gives you a `CallResult` with an `ErrorCode` rather than an HTTP
response, which is what you want when the caller does not care whether the
answer came over HTTP or from a subprocess.

## Gate a deploy on it

```bash
php artisan laranail::polyglot.health
```

Exits non-zero if any service is down. A **faked** transport reports itself as
faked rather than healthy, so this cannot pass green against a fake.

## Test without the service

```php
Polyglot::fake(['inference' => ['vector' => [0.1, 0.2, 0.3]]]);

$this->post('/documents', ['body' => 'hello'])->assertOk();

Polyglot::assertSentTo('inference');
Polyglot::assertSentTimes('inference', 1);
```

## TLS against an internal proxy

```php
'verify_ssl' => true,
'ca_cert'    => '/etc/ssl/internal-root.crt',
```

Trust one extra root rather than turning verification off. `verify_ssl => false`
is refused in production and reported by `doctor`; a set-but-missing `ca_cert`
falls back to the system trust store, never to `false`.

---
[← Docs index](../../README.md#documentation)
