<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Commands;

use Illuminate\Process\Factory as ProcessFactory;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Polyglot\Contracts\HttpClient;
use Simtabi\Laranail\Polyglot\Contracts\ProcessRunner;
use Simtabi\Laranail\Polyglot\Contracts\RuntimeResolver;
use Simtabi\Laranail\Polyglot\Contracts\ScriptResolver;
use Simtabi\Laranail\Polyglot\Exceptions\PolyglotException;
use Simtabi\Laranail\Polyglot\Support\PolyglotConfig;
use Throwable;

/**
 * Answers the questions you actually have during an incident.
 *
 * Not "is a URL set" — the config file always looks fine — but which services
 * are reachable, whether TLS is verifying or quietly turned off, whether a
 * configured auth scheme is missing its credential, and which interpreter a
 * script would really run under. Those are the arrangements that leave an
 * application looking completely healthy while failing.
 */
final class DoctorCommand extends Command
{
    use SupportsNamespacedNames;

    protected $name = 'laranail::polyglot.doctor';

    /** @var list<string> */
    protected array $commandAliases = ['python:doctor'];

    protected $description = 'Report every configured service and target, and anything misconfigured.';

    public function handle(
        PolyglotConfig $config,
        HttpClient $http,
        ProcessRunner $process,
        ScriptResolver $scripts,
        RuntimeResolver $runtimes,
        ProcessFactory $processes,
    ): int {
        $display = $this->services->display();
        $display->header('laranail/polyglot');

        $problems = [];

        $display->keyValue([
            'Default service' => $config->string('default', 'fastapi'),
            'Services' => (string) count($http->names()),
            'Process transport' => $process->isEnabled() ? 'enabled' : 'disabled',
            'Callbacks' => $config->bool('callbacks.enabled', false) ? 'enabled' : 'disabled',
        ]);

        $problems = [...$problems, ...$this->reportServices($http)];
        $problems = [...$problems, ...$this->reportProcess($process, $scripts, $runtimes, $config, $processes)];
        $problems = [...$problems, ...$this->reportCallbacks($config)];

        foreach ($problems as $problem) {
            $display->error($problem);
        }

        if ($problems !== []) {
            return self::FAILURE;
        }

        $display->success('No problems found.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function reportServices(HttpClient $http): array
    {
        $names = $http->names();

        if ($names === []) {
            return ['No services are configured under laranail.polyglot.services.'];
        }

        $problems = [];
        $rows = [];

        foreach ($names as $name) {
            try {
                $definition = $http->definition($name);
            } catch (PolyglotException $e) {
                $problems[] = $e->getMessage();

                continue;
            }

            if (($problem = $definition->baseUrlProblem()) !== null) {
                $problems[] = "Service [{$name}]: {$problem}.";
            }

            if (! $definition->auth->isComplete()) {
                $problems[] = "Service [{$name}]: auth scheme [{$definition->auth->scheme->value}] "
                    . 'is configured without its credential, so requests would go out unauthenticated.';
            }

            if (! $definition->verifySsl) {
                $problems[] = "Service [{$name}]: TLS verification is off. Prefer a ca_cert, "
                    . 'which keeps verification on and trusts one extra root.';
            }

            if ($this->isMetadataAddress($definition->baseUrl)) {
                $problems[] = "Service [{$name}]: base_url points at a cloud metadata address.";
            }

            $report = $http->report($name);

            $rows[] = [
                $name,
                $definition->baseUrl,
                $definition->auth->scheme->value,
                $definition->tlsMode(),
                $report->healthy ? 'healthy' : 'unhealthy',
                $report->roundTripMs === null ? '—' : $report->roundTripMs . ' ms',
            ];

            if (! $report->healthy) {
                $problems[] = "Service [{$name}] is not answering its health contract: {$report->error}";
            }
        }

        if ($rows !== []) {
            $this->services->display()->displayTable(
                ['Service', 'Base URL', 'Auth', 'TLS', 'Health', 'RTT'],
                $rows,
            );
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function reportProcess(
        ProcessRunner $process,
        ScriptResolver $scripts,
        RuntimeResolver $runtimes,
        PolyglotConfig $config,
        ProcessFactory $processes,
    ): array {
        if (! $process->isEnabled()) {
            return [];
        }

        $problems = [];
        $rows = [];

        if ($config->bool('process.allow_arbitrary_paths', false)) {
            $problems[] = 'process.allow_arbitrary_paths is on: any path inside the root may be run. '
                . 'The allow-list is the safer default.';
        }

        if ($config->bool('process.inherit_env', false)) {
            $problems[] = 'process.inherit_env is on: the child receives the full parent environment, '
                . 'including APP_KEY and database credentials.';
        }

        foreach ($scripts->names() as $name) {
            try {
                $resolved = $scripts->resolve($name);
                $rows[] = [
                    $name,
                    $resolved->path,
                    $resolved->isDirectlyExecutable() ? '(runs itself)' : implode(' ', $resolved->prefix),
                    $this->runtimeVersion($processes, $resolved->prefix, $config),
                ];
            } catch (Throwable $e) {
                $problems[] = "Script [{$name}]: " . $e->getMessage();
            }
        }

        if ($rows !== []) {
            $this->services->display()->displayTable(['Target', 'Path', 'Runtime', 'Version'], $rows);
        }

        if ($scripts->names() === [] && ! $config->bool('process.allow_arbitrary_paths', false)) {
            $problems[] = 'The process transport is enabled but no scripts are registered.';
        }

        try {
            $runtimes->resolve();
        } catch (Throwable $e) {
            $problems[] = 'Default runtime: ' . $e->getMessage();
        }

        return $problems;
    }

    /**
     * @return list<string>
     */
    private function reportCallbacks(PolyglotConfig $config): array
    {
        if (! $config->bool('callbacks.enabled', false)) {
            return [];
        }

        $secrets = $config->stringList('callbacks.secrets');

        $this->services->display()->keyValue([
            'Callback prefix' => $config->string('callbacks.prefix', 'api/python'),
            'Callback secrets' => $secrets === [] ? 'none' : count($secrets) . ' configured',
            'Tolerance' => $config->int('callbacks.tolerance', 300) . 's',
        ]);

        if ($secrets === []) {
            return ['Callbacks are enabled but no secret is configured, so every delivery is refused.'];
        }

        return [];
    }

    /**
     * Ask a runtime what it is.
     *
     * ## `--version` is not universal, and this reads stderr on purpose
     *
     * Every runtime answers a different way, and two of them answer on the
     * wrong stream:
     *
     * ```
     * python3 --version    →  stdout
     * node --version       →  stdout
     * go version           →  a SUBCOMMAND, not a flag; `go --version` is an error
     * java -version        →  ONE dash, and it writes to STDERR
     * docker --version     →  stdout
     * ```
     *
     * So the probe argument is configurable per runtime, and both streams are
     * read. A doctor that only read stdout would report Java as "unknown" on a
     * perfectly working installation.
     *
     * Through illuminate/process with an array command, like everything else
     * here — the package never builds a command string, and an arch test holds
     * that line.
     *
     * @param list<string> $prefix
     */
    private function runtimeVersion(ProcessFactory $processes, array $prefix, PolyglotConfig $config): string
    {
        if ($prefix === []) {
            // A compiled binary. There is no runtime to interrogate, and asking
            // the target itself for a version would run user code from doctor.
            return 'n/a';
        }

        $executable = $prefix[0];

        // Only a path can be checked. A bare command such as `docker` is
        // resolved from $PATH by the process layer, which is the point of
        // allowing it.
        if (str_starts_with($executable, '/') && (! is_file($executable) || ! is_executable($executable))) {
            return 'unavailable';
        }

        $result = $processes->newPendingProcess()
            ->timeout(5)
            ->run([...$prefix, ...$this->versionArguments($executable, $config)]);

        // Both streams: `java -version` writes to stderr on success.
        $output = trim($result->output() . ' ' . $result->errorOutput());

        if ($output === '') {
            return 'unknown';
        }

        // First line only: `java -version` prints three. strtok returns false
        // only for an empty subject, which the guard above already excluded.
        $first = strtok($output, "\n");

        return is_string($first) ? $first : 'unknown';
    }

    /**
     * The arguments that make a runtime print its version.
     *
     * Configurable, with defaults for the shapes that differ from
     * `--version` — because `go --version` is an error and `java --version`
     * only works on 9 and later.
     *
     * @return list<string>
     */
    private function versionArguments(string $executable, PolyglotConfig $config): array
    {
        $configured = $config->array('process.version_probes');
        $name = basename($executable);

        foreach ($configured as $match => $arguments) {
            if (is_string($match) && $match === $name && is_array($arguments)) {
                return array_values(array_filter($arguments, is_string(...)));
            }
        }

        return match ($name) {
            'go' => ['version'],
            'java' => ['-version'],
            default => ['--version'],
        };
    }

    private function isMetadataAddress(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && in_array($host, ['169.254.169.254', 'metadata.google.internal'], true);
    }
}
