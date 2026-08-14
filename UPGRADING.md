# Upgrade guide

## Coming from `laranail/toolkit`

The HTTP half of this package lived in toolkit as
`Toolkit\Services\PythonApiService`. It outgrew being one service among twenty:
a microservice client that also needs authentication, a health surface, a
process transport and a callback endpoint is its own concern.

```diff
+   composer require laranail/python
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
