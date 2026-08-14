# Add a runtime

Node, Go, Ruby, a compiled binary, or a container. None of it needs code.

## Name the runtime

```php
// config/laranail/polyglot.php
'process' => [
    'runtimes' => [
        'default' => env('POLYGLOT_BIN'),
        'node'    => base_path('node_modules/.bin/tsx'),
        'go'      => '/usr/local/bin/my-tool',
        'docker'  => ['docker', 'run', '--rm', '--network=none', 'my-image'],
        'binary'  => [],
    ],
],
```

A runtime is **a list of words**, not an interpreter path:

| Value | Means | Path-checked |
|---|---|---|
| `'/usr/bin/python3'` | A single executable | yes — `is_file()` + `is_executable()` |
| `['docker','run','--rm',$image]` | A command prefix | **no** |
| `[]` | The script runs itself | n/a |

The longer list is deliberately not path-checked: `docker` is meant to come from
`$PATH`, and the words after it are arguments rather than files. The command is
still assembled as an array and never passed through a shell.

## Point a script at it

```php
'scripts' => [
    'transcode' => [
        'path'    => 'scripts/transcode.ts',
        'runtime' => 'node',
        'timeout' => 300,
    ],
],
```

## Teach `doctor` how to ask its version

```php
'version_probes' => [
    'ruby'  => ['--version'],
    'swift' => ['--version'],
],
```

`--version` is **not universal**, which is the whole reason this key exists:

- `go --version` is an error — the subcommand is `go version`;
- `java -version` takes **one** dash and writes to **stderr**.

Both streams are read and those two have built-in defaults. A probe that only
read stdout reported Java as "unknown" on a working installation, which sends
you looking at the wrong thing entirely.

## The contract your script must honour

Whatever the language, the shape is the same:

1. Read a JSON object from **stdin**.
2. Write a JSON object to **stdout**.
3. Exit 0 for success, non-zero for failure.
4. Put diagnostics on **stderr** — and nothing secret, because stderr is what
   surfaces in a failure.

```typescript
// scripts/transcode.ts
const payload = JSON.parse(await new Response(Bun.stdin.stream()).text());
console.log(JSON.stringify({ ok: true, path: transcode(payload.input) }));
```

Nothing here is Python-specific, and nothing in the transport, the allow-list,
the HMAC callbacks or the replay guard ever was. That is what the rename was
about.

## Check it

```bash
php artisan laranail::polyglot.doctor
php artisan laranail::polyglot.run transcode --payload='{"input":"a.mov"}'
```

`run` goes through the same resolver, allow-list and clamps as a call from
application code, so what you see there is what production does.

---
[← Docs index](../../README.md#documentation)
