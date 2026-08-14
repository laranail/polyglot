# Commands

Five, all named `laranail::polyglot.<command>`.

| Command | Answers |
|---|---|
| `laranail::polyglot.doctor` | Is everything configured, reachable and permitted? |
| `laranail::polyglot.health` | Are the HTTP services up? |
| `laranail::polyglot.run` | Run a registered target from the shell |
| `laranail::polyglot.install` | Scaffold the config and directories |
| `laranail::polyglot.make-service` | Scaffold a new service from a stub |

**None of them carries a short alias.** `polyglot:doctor` would claim a name any
package or application could also want, and Artisan's registry is a flat map
where the loser is replaced without a word. The renamed package briefly shipped
`python:doctor` and friends; those are gone.

> The `::` works because Symfony resolves an exact command name before its
> `:`-splitting lookup. Getting the name *past* `Command::validateName()` —
> whose pattern rejects the empty segment in `::` — is what
> `SupportsNamespacedNames` is for.

## `doctor`

The first thing to run when a call fails and you do not know which half.

```bash
php artisan laranail::polyglot.doctor
```

Reports every configured service and target, the resolved runtime for each, the
callback prefix, and anything misconfigured — a script whose file is missing, a
runtime whose binary is not executable, a service with TLS verification off.

### The version probe reads both streams

`--version` is not universal. `go --version` is an **error** — the subcommand is
`go version` — and `java -version` takes one dash and writes to **stderr**.

A probe that only read stdout reported Java as "unknown" on a working
installation, which sends you looking at the wrong thing. Both streams are read,
those two have built-in defaults, and anything else that disagrees goes in
`process.version_probes`.

## `health`

```bash
php artisan laranail::polyglot.health
```

Calls each HTTP service's health contract and reports base URL, TLS mode and
round-trip time. Exits non-zero if any service is down, so it works as a
deployment gate.

A **faked** transport reports itself as faked rather than healthy — a green
health check backed by a fake is worse than a red one.

## `run`

```bash
php artisan laranail::polyglot.run embed --payload='{"text":"hello"}'
```

Replaces the "ssh in and run the script by hand" step, which is where a wrong
interpreter, a wrong working directory and an unset environment variable all
come from. It goes through the same resolver, the same allow-list and the same
clamps as a call from application code, so what you see here is what production
does.

## `install`

```bash
php artisan laranail::polyglot.install
```

Publishes the config and creates the directories. Idempotent.

## `make-service`

```bash
php artisan laranail::polyglot.make-service inference
```

Scaffolds `polyglot/services/inference` from a stub — currently FastAPI — with
the health contract this package expects already wired, so `health` passes on a
freshly generated service rather than after you work out the shape.

---
[← Docs index](../../README.md#documentation)
