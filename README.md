# laranail/polyglot

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/polyglot.svg)](https://packagist.org/packages/laranail/polyglot)
[![Tests](https://github.com/laranail/polyglot/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/polyglot/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/polyglot/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/polyglot/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> A Python bridge for Laravel — call FastAPI/Flask services over HTTP, run scripts in a virtualenv behind a hardened process clamp, and receive HMAC-signed callbacks when long work finishes.

Requires PHP `^8.4.1 || ^8.5` on Laravel `^13`.

## Install

```bash
composer require laranail/polyglot
php artisan laranail::polyglot.install
```

## Quick start

```php
use Simtabi\Laranail\Polyglot\Facades\Polyglot;

// A configured HTTP client — Laravel's own, so attach(), sink() and streaming work.
$vector = Polyglot::service('fastapi')->post('/embed', ['text' => $text])->json('vector');

// Or the transport-agnostic call, which returns a result rather than throwing.
$result = Polyglot::run('fastapi:embed', ['text' => $text]);
$result->ok ? $result->get('vector') : report($result->message);

// A local script, from an allow-list. Payload on stdin, JSON back.
$result = Polyglot::run('embed', ['text' => $text]);

// Work too slow for a request: submit, and be called back.
$handle = Polyglot::submit('fastapi:train', ['epochs' => 50], route('python.done'));
```

```bash
php artisan laranail::polyglot.doctor   # every service, its TLS mode, auth, and a live probe
```

## <a name="documentation"></a>Documentation

Hosted at **[opensource.simtabi.com/documentation/laranail/polyglot](https://opensource.simtabi.com/documentation/laranail/polyglot/)**.

### Guides
- [Installation](docs/installation.md) — requirements, what to publish, what stays off
- [Getting started](docs/getting-started.md) — the two transports and the first calls
- [Configuration](docs/configuration.md) — every key and its environment variable
- [Security](docs/security.md) — the threat model and every guard, with the reasoning
- [Architecture](docs/architecture.md) — the transports, the resolver, and what the rename changed
- [Release](docs/release.md) — cutting a version

### Reference
- [The HTTP transport](docs/tools/http.md) — the service registry, auth, TLS, health
- [The process transport](docs/tools/process.md) — runtimes, the allow-list, the clamps
- [Results and errors](docs/tools/results.md) — `CallResult`, the eight error codes, redaction
- [Signed callbacks](docs/tools/callbacks.md) — HMAC, the timestamp window, the replay guard
- [Commands](docs/tools/commands.md) — doctor, health, run, install, make-service

### Recipes
- [Call an HTTP service](docs/recipes/call-a-service.md)
- [Add a runtime](docs/recipes/add-a-runtime.md)

## Security

This package executes local processes and can expose an unauthenticated HTTP
endpoint, so both are off until you turn them on, and the reasoning behind every
guard is written down in [docs/security.md](docs/security.md). The short version:

- Scripts are named from an allow-list; a caller cannot express a path at all.
- Commands are arrays, never strings, so nothing reaches a shell.
- Payloads travel on stdin, never argv — `/proc/<pid>/cmdline` is world-readable.
- The child gets an allow-listed environment, not yours.
- Callbacks need a valid HMAC over the raw body, a fresh timestamp, **and** an
  unused delivery id. A timestamp window alone does not stop replay.

Report vulnerabilities per [SECURITY.md](SECURITY.md) (opensource@simtabi.com).

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md). Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
