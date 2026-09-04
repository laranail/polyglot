<?php

declare(strict_types=1);

/*
 * Merged under the namespaced key "laranail.polyglot" per the laranail
 * convention: read every value as config('laranail.polyglot.*'). When published,
 * this file lands at config/laranail/python.php so Laravel loads it under the
 * same key.
 *
 * env() is called here and nowhere else in the package. Reading env() at any
 * other point returns null the moment the host runs `config:cache`.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Default service
    |--------------------------------------------------------------------------
    |
    | Which named service Polyglot::call() reaches when a target names no service.
    |
    */

    'default' => env('POLYGLOT_DEFAULT_SERVICE', 'fastapi'),

    /*
    |--------------------------------------------------------------------------
    | Shared defaults
    |--------------------------------------------------------------------------
    |
    | Applied to every service that does not override them.
    |
    */

    'defaults' => [
        'timeout'            => env('POLYGLOT_TIMEOUT', 30),
        'connect_timeout'    => env('POLYGLOT_CONNECT_TIMEOUT', 5),
        'max_response_bytes' => 8388608,
        'json_depth'         => 64,
    ],

    /*
    |--------------------------------------------------------------------------
    | Services
    |--------------------------------------------------------------------------
    |
    | Every service is data. Adding one is an entry here, not code.
    |
    | auth.scheme — none | bearer | api_key | basic. A scheme configured without
    | its credential is reported as incomplete by `laranail::polyglot.doctor`
    | rather than silently sending an unauthenticated request.
    |
    | verify_ssl — set false only against a local self-signed proxy. `ca_cert`
    | is the better answer: it keeps verification on and trusts one extra root.
    |
    */

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
            'auth'            => [
                'scheme'   => env('POLYGLOT_FASTAPI_AUTH', 'none'),
                'token'    => env('POLYGLOT_FASTAPI_TOKEN'),
                'header'   => env('POLYGLOT_FASTAPI_AUTH_HEADER', 'X-API-Key'),
                'username' => env('POLYGLOT_FASTAPI_USERNAME'),
                'password' => env('POLYGLOT_FASTAPI_PASSWORD'),
            ],
            'headers' => [],
        ],

        'flask' => [
            'base_url'        => env('POLYGLOT_FLASK_URL', 'http://127.0.0.1:5000'),
            'timeout'         => env('POLYGLOT_FLASK_TIMEOUT'),
            'connect_timeout' => env('POLYGLOT_FLASK_CONNECT_TIMEOUT'),
            'verify_ssl'      => env('POLYGLOT_FLASK_VERIFY_SSL', true),
            'ca_cert'         => env('POLYGLOT_FLASK_CA_CERT'),
            'health_path'     => env('POLYGLOT_FLASK_HEALTH_PATH', '/health'),
            'health_key'      => env('POLYGLOT_FLASK_HEALTH_KEY', 'status'),
            'healthy_value'   => env('POLYGLOT_FLASK_HEALTHY_VALUE', 'healthy'),
            'retry_times'     => env('POLYGLOT_FLASK_RETRY_TIMES', 3),
            'retry_sleep_ms'  => env('POLYGLOT_FLASK_RETRY_SLEEP_MS', 100),
            'auth'            => [
                'scheme'   => env('POLYGLOT_FLASK_AUTH', 'none'),
                'token'    => env('POLYGLOT_FLASK_TOKEN'),
                'header'   => env('POLYGLOT_FLASK_AUTH_HEADER', 'X-API-Key'),
                'username' => env('POLYGLOT_FLASK_USERNAME'),
                'password' => env('POLYGLOT_FLASK_PASSWORD'),
            ],
            'headers' => [],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Local process transport
    |--------------------------------------------------------------------------
    |
    | Off by default. This is arbitrary code execution reachable from
    | configuration, so it takes a deliberate `true` rather than arriving with
    | the package.
    |
    | scripts — a map of LOGICAL NAME => path relative to `root`. Callers name a
    | script; they never pass a path. Adding one is a config entry, the same
    | property the service registry has.
    |
    | allow_arbitrary_paths — swaps the allow-list for a root clamp, where any
    | path is accepted as long as realpath() lands inside `root`. realpath()
    | resolves both ../ and symlinks, so a symlink planted inside the root and
    | pointing at /etc is still refused.
    |
    | inherit_env — false means the child gets only the values below, not the
    | parent's environment. A Python script does not need APP_KEY or
    | DB_PASSWORD, and a traceback prints whatever it can reach.
    |
    | log_stderr — false because a Python traceback embeds local variables and a
    | requests exception embeds the full URL, query-string token included.
    |
    */

    'process' => [
        'enabled'               => env('POLYGLOT_PROCESS_ENABLED', false),
        'root'                  => env('POLYGLOT_PROCESS_ROOT'),
        'allow_arbitrary_paths' => false,
        'allow_path_lookup'     => false,
        'timeout'               => env('POLYGLOT_PROCESS_TIMEOUT', 60),
        'idle_timeout'          => env('POLYGLOT_PROCESS_IDLE_TIMEOUT', 30),
        'max_output_bytes'      => 8388608,
        'log_stderr'            => false,
        'stderr_max_chars'      => 2000,
        'inherit_env'           => false,
        'env'                   => [],

        /*
        | A runtime is the words that go BEFORE the target, and it is not always
        | one executable:
        |
        |   'python' => '/usr/bin/python3',                    an interpreter
        |   'node'   => base_path('node_modules/.bin/tsx'),    a project-local tool
        |   'docker' => ['docker', 'run', '--rm', 'my-image'], a container
        |   'binary' => [],                                    the target runs itself
        |
        | A single path keeps its old guarantees: absolute (unless
        | allow_path_lookup), and an executable file. A longer list is not
        | path-checked — `docker` is meant to come from $PATH, and the words
        | after it are arguments, not files. Nothing is ever passed through a
        | shell either way; the command is always built as an array.
        |
        | With nothing set, the fallback is {root}/.venv/bin/python, which is
        | what a Python project in the host repo almost always has. That is
        | Python's convention; another runtime names its own here.
        */
        'runtimes' => [
            'default' => env('POLYGLOT_BIN'),
            // 'node'   => base_path('node_modules/.bin/tsx'),
            // 'docker' => ['docker', 'run', '--rm', '--network=none', 'my-image'],
            // 'binary' => [],
        ],

        /*
        | How to ask a runtime for its version, for `doctor`. `--version` is not
        | universal: `go --version` is an error (the subcommand is `go version`)
        | and `java -version` takes one dash and writes to STDERR. Both streams
        | are read, and these two have built-in defaults — this is for anything
        | else that disagrees.
        */
        'version_probes' => [
            // 'ruby'  => ['--version'],
            // 'swift' => ['--version'],
        ],

        'scripts' => [
            // 'embed' => 'scripts/embed.py',
            // 'train' => [
            //     'path'         => 'scripts/train.py',
            //     'runtime'      => 'ml',
            //     'timeout'      => 900,
            //     'allows_flags' => false,
            //     'env'          => ['MODEL_DIR' => '/var/models'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound callbacks
    |--------------------------------------------------------------------------
    |
    | Long-running work finishes asynchronously and has to report back. The
    | route is NOT registered unless this is enabled — an unauthenticated POST
    | endpoint in every application that never uses one is a standing liability.
    |
    | secrets — a list, verified against in order, signed with the first.
    | Rotate by prepending the new one, deploying, then dropping the tail.
    |
    | tolerance — how far a timestamp may be from now, in seconds. This bounds
    | replay but does not stop it, which is what the delivery-id cache is for.
    |
    */

    'callbacks' => [
        'enabled'          => env('POLYGLOT_CALLBACKS_ENABLED', false),
        'prefix'           => env('POLYGLOT_CALLBACK_PREFIX', 'api/polyglot'),
        'middleware'       => ['api'],
        'rate_limit'       => '60,1',
        'secrets'          => array_values(array_filter([env('POLYGLOT_CALLBACK_SECRET')])),
        'algo'             => 'sha256',
        'tolerance'        => 300,
        'signature_header' => 'X-Laranail-Signature',
        'timestamp_header' => 'X-Laranail-Timestamp',
        'id_header'        => 'X-Laranail-Id',
        'max_body_bytes'   => 1048576,
        'replay_store'     => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Async tasks
    |--------------------------------------------------------------------------
    */

    'tasks' => [
        'store'      => null,
        'ttl'        => 86400,
        'poll_path'  => '/tasks/{id}',
        'status_key' => 'status',
        'result_key' => 'result',
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Off by default: a service called once per request turns this into a
    | disk-space incident rather than an audit trail.
    |
    */

    'logging' => [
        'enabled' => env('POLYGLOT_LOGGING', false),
        'channel' => null,
    ],

];
