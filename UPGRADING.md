# Upgrade guide

## Coming from `laranail/python`

Same package, renamed. Nothing in the transport, the allow-list, the HMAC
callbacks or the replay guard was ever Python-specific — the name was.

```diff
-   composer require laranail/python
+   composer require laranail/polyglot
```

### What you have to change

| Was | Is |
|---|---|
| `Simtabi\Laranail\Python\…` | `Simtabi\Laranail\Polyglot\…` |
| `Python` facade | `Polyglot` facade |
| `config('laranail.python.*')` | `config('laranail.polyglot.*')` |
| `PYTHON_*` env vars | `POLYGLOT_*` |
| `process.interpreters` | `process.runtimes` |
| a script's `interpreter` key | `runtime` |
| `$client->fastapi()` / `->flask()` | `Polyglot::service('fastapi')` |

### Three that will not error, and will change behaviour

These fail quietly rather than loudly, so check each one:

- **The callback route prefix default moved from `api/python` to
  `api/polyglot`.** If you never set `POLYGLOT_CALLBACK_PREFIX`, the endpoint
  your external caller posts to has changed and it will start getting 404s. Set
  the env var to `api/python` to keep the old path.

- **The cache key namespace moved from `laranail:python:` to
  `laranail:polyglot:`.** In-flight async task handles and unspent replay-guard
  claims are stranded under the old keys — so a delivery id already used
  becomes usable again. Drain the queue before deploying, or accept a
  one-deployment replay window.

- **The short command aliases are gone.** `python:doctor`, `python:run`,
  `python:health`, `python:install` and `python:make-service` are removed and
  have **no** `polyglot:` replacement. Use the full names —
  `laranail::polyglot.doctor` and so on. A bare `polyglot:doctor` would claim a
  name any package or application could also want, and Artisan's registry is a
  flat map where the loser is replaced without a word. Update any deploy script
  or cron entry that used the short form.

Scaffolds also move from `python/services/` to `polyglot/services/`, which
affects new services only.

## Coming from `laranail/toolkit`

The HTTP half of this package lived in toolkit as
`Toolkit\Services\PythonApiService`. It outgrew being one service among twenty:
a microservice client that also needs authentication, a health surface, a
process transport and a callback endpoint is its own concern.

```diff
+   composer require laranail/polyglot
```

### Config keys

**Env var names are unchanged**, so an existing `.env` keeps working. Only the
config path moves.

| Old | New |
|---|---|
| `laranail.toolkit.python.services.fastapi.base_url` | `laranail.polyglot.services.fastapi.base_url` |
| `laranail.toolkit.python.services.*.timeout` | `laranail.polyglot.services.*.timeout` |
| `laranail.toolkit.python.services.*.verify_ssl` | `laranail.polyglot.services.*.verify_ssl` |
| `laranail.toolkit.python.services.*.ca_cert` | `laranail.polyglot.services.*.ca_cert` |
| `laranail.toolkit.python.services.*.health_path` | `laranail.polyglot.services.*.health_path` |
| `laranail.toolkit.python.services.*.health_key` | `laranail.polyglot.services.*.health_key` |
| `laranail.toolkit.python.services.*.healthy_value` | `laranail.polyglot.services.*.healthy_value` |
| `laranail.toolkit.python.services.*.retry_times` | `laranail.polyglot.services.*.retry_times` |
| `laranail.toolkit.python.services.*.retry_sleep_ms` | `laranail.polyglot.services.*.retry_sleep_ms` |

### Classes

| Old | New |
|---|---|
| `Toolkit\Services\PythonApiService` | `Python\Http\HttpClientService` |
| `Toolkit\Services\Contracts\PythonApiServiceInterface` | `Python\Contracts\HttpClient` |
| `Toolkit\Services\PythonServiceDefinition` | `Python\Http\ServiceDefinition` |
| `Toolkit\Exceptions\PythonApiException` | `Python\Exceptions\UnknownServiceException` / `MissingBaseUrlException` |
| `Toolkit::pythonApi()->service($n)` | `Polyglot::service($n)` |

### Two behaviour changes

**Timeout default.** The old client fell back to
`laranail.toolkit.http.request_timeout`, shared with everything else in toolkit.
This package owns its own `laranail.polyglot.defaults.timeout` (30s). If you had
tuned the toolkit-wide value, set it here too.

**Client errors are no longer retried, and no longer throw.** Laravel's
`retry()` throws on every non-2xx once `tries > 1`, so `retry(3, 100)` hit a
service three times with a request that could never succeed — a 422 does not
become a 200 — and then raised it as a `RequestException`. Retries are now
limited to connection failures and 5xx, and a non-2xx comes back as a response.

If you were catching `RequestException` around a call to get at a 4xx body, you
now check the response instead:

```diff
- try {
-     $response = Toolkit::pythonApi()->fastapi()->post('/predict', $data);
- } catch (RequestException $e) {
-     $detail = $e->response->json('detail');
- }
+ $result = Polyglot::run('fastapi:predict', $data);
+ $detail = $result->ok ? null : $result->get('detail');
```
