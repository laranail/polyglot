<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Commands;

use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * Publish the config and say what to do next.
 */
final class InstallCommand extends Command
{
    use SupportsNamespacedNames;

    protected $name = 'laranail::polyglot.install';

    protected $description = 'Publish the laranail/python configuration.';

    public function handle(): int
    {
        $this->callSilently('vendor:publish', ['--tag' => 'laranail::polyglot-config']);

        $display = $this->services->display();
        $display->success('Published config/laranail/python.php.');

        $display->list([
            'Point laranail.polyglot.services.* at your services, and set an auth scheme if they need one.',
            'Run `php artisan laranail::polyglot.doctor` to confirm they answer.',
            'Local scripts stay off until laranail.polyglot.process.enabled is true — it is code execution.',
            'Callbacks stay off until laranail.polyglot.callbacks.enabled is true and a secret is set.',
        ], 'Next');

        return self::SUCCESS;
    }
}
