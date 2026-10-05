<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Commands;

use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\InteractsWithConsoleWriter;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\InteractsWithConsoleServices;
use Simtabi\Laranail\Package\Tools\Commands\InstallCommand as PackageToolsInstallCommand;

/**
 * Publish the config and say what to do next.
 *
 * The base is package-tools' install command, which carries the `::` name support. laranail/console's
 * display API (`$this->services`) and managed run lifecycle come from its two traits rather than its
 * base class, so neither package has to depend on the other. `handle()` is this command's own: the
 * base's generic publish pipeline would print different steps, and the output here is the contract.
 */
final class InstallCommand extends PackageToolsInstallCommand
{
    use InteractsWithConsoleServices;
    use InteractsWithConsoleWriter;

    public const string SIGNATURE = 'laranail::polyglot.install';

    public const string DESCRIPTION = 'Publish the laranail/polyglot configuration.';

    public function __construct(Package $package)
    {
        // Listed in `php artisan list`, as it always has been: the base hides install commands by
        // default, so visibility is passed explicitly rather than inherited.
        parent::__construct($package, self::SIGNATURE, hidden: false);

        // The base writes `Install {package}` as the description during construction; restore the
        // one this command has always shown. Both the property and Symfony's copy are set, because
        // the parent constructor has already pushed the property through setDescription().
        $this->description = self::DESCRIPTION;
        $this->setDescription(self::DESCRIPTION);

        // Booted eagerly, as console's own base does, so `$this->services` exists straight after
        // construction rather than only once run() has been entered.
        $this->bootConsoleSupport();
    }

    public function handle(): int
    {
        $this->callSilently('vendor:publish', ['--tag' => 'laranail::polyglot-config']);

        $display = $this->services->display();
        $display->success('Published config/laranail/polyglot.php.');

        $display->list([
            'Point laranail.polyglot.services.* at your services, and set an auth scheme if they need one.',
            'Run `php artisan laranail::polyglot.doctor` to confirm they answer.',
            'Local scripts stay off until laranail.polyglot.process.enabled is true — it is code execution.',
            'Callbacks stay off until laranail.polyglot.callbacks.enabled is true and a secret is set.',
        ], 'Next');

        return self::SUCCESS;
    }
}
