# The process transport

Runs a registered script as a subprocess: JSON on stdin, JSON on stdout.

**Off by default.** It is arbitrary code execution reachable from configuration,
so switching it on is a decision rather than a default.

```php
'process' => ['enabled' => env('POLYGLOT_PROCESS_ENABLED', false)],
```

## Registering a target

```php
'scripts' => [
    'embed' => 'scripts/embed.py',

    'train' => [
        'path'         => 'scripts/train.py',
        'runtime'      => 'ml',
        'timeout'      => 900,
        'allows_flags' => false,
        'env'          => ['MODEL_DIR' => '/var/models'],
    ],
],
```

Only registered names run. A name that is not a key never resolves — the
allow-list *is* the config, so there is no path from a request parameter to an
arbitrary file.

## Running one

```php
use Simtabi\Laranail\Polyglot\Facades\Polyglot;

$result = Polyglot::run('embed', ['text' => 'hello']);

$result->failed();
$result->get('vector');
$result->throw();          // if you would rather it raised
```

## Runtimes are config, not code

```php
'runtimes' => [
    'default' => env('POLYGLOT_BIN'),
    'node'    => base_path('node_modules/.bin/tsx'),
    'docker'  => ['docker', 'run', '--rm', '--network=none', 'my-image'],
    'binary'  => [],
],
```

A runtime is **a list of words**, not an interpreter path. That is the one
structural change the rename brought, and it is what makes the package not about
Python:

| Value | Means |
|---|---|
| `'/usr/bin/python3'` | A single path — checked with `is_file()` and `is_executable()` |
| `['docker','run','--rm',$image]` | A command prefix — **not** path-checked |
| `[]` | A compiled binary that runs itself |

A single path keeps every old check. A longer list is deliberately not
path-checked, because `docker` is meant to come from `$PATH` and the words after
it are arguments rather than files.

With nothing set the fallback is `{root}/.venv/bin/python`, which is what a
Python project in the host repo almost always has. That is Python's convention;
another runtime names its own.

## The security clamps

These are the reason the transport is worth using rather than calling
`exec()` yourself:

- **The command is built as an array and never passed through a shell.** No
  interpolation, no quoting to get wrong, no `;` to inject.
- **The payload goes on stdin, never on argv.** Arguments are visible in the
  process table to every user on the box; stdin is not.
- **A root clamp** — a resolved script path outside the configured root is
  refused, so a `../` in a config value does not reach the filesystem.
- **An environment allow-list** — the child inherits only what you name. It does
  **not** get `APP_KEY`, and a test asserts that.
- **A flag guard** — `allows_flags: false` refuses arguments that look like
  options, so a caller-supplied value cannot become `--output=/etc/passwd`.
- **A timeout and an idle timeout**, both clamped.

## A timeout is a result, not an exception

```php
$result = Polyglot::run('train', $payload);

if ($result->failed() && $result->errorCode === ErrorCode::Timeout) {
    // handled like any other failure
}
```

A hung script is the reason the clamp exists, so a timeout is an **expected**
outcome. Symfony's `ProcessTimedOutException` used to propagate straight out of
`run()`, which made the timeout the single failure mode a caller could not
handle the way it handles every other.

Both Symfony's and Laravel's timeout classes are caught — they are unrelated
types, Laravel's extending `RuntimeException`, so catching either alone left the
other escaping. The rebuilt message also drops the full command line the vendor
exception embeds, which named the interpreter and script path verbatim.

## Testing it

Two suites, and neither substitutes for the other:

- **`ProcessInjectionTest`** proves the guards refuse what they should, using
  PHP as the interpreter.
- **`RealInterpreterTest`** proves what they *permit* actually works, against a
  real Python interpreter, in the `python` group.

The timeout bug above is exactly what the gap between them was hiding.

---
[← Docs index](../../README.md#documentation)
