<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;
use Simtabi\Laranail\Polyglot\Commands\InstallCommand;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\InteractsWithConsoleWriter;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\InteractsWithConsoleServices;
use Simtabi\Laranail\Package\Tools\Commands\InstallCommand as PackageToolsInstallCommand;

/**
 * The install command's observable surface, pinned.
 *
 * Its base class moved from laranail/console's `Command` to laranail/package-tools'
 * `InstallCommand`, with console's display API kept by `use`-ing its two traits. A base swap is
 * where a name, an option, the listing visibility or a line of output changes without anyone
 * deciding it should, so every one of those is asserted here against what the command did before.
 */
function polyglotInstallCommand(): Command
{
    $command = Artisan::all()['laranail::polyglot.install'] ?? null;

    expect($command)->toBeInstanceOf(InstallCommand::class);

    /** @var Command $command */
    return $command;
}

it('keeps its name, aliases, description and listing visibility', function (): void {
    $command = polyglotInstallCommand();

    expect($command->getName())->toBe('laranail::polyglot.install')
        ->and($command->getAliases())->toBe([])
        ->and($command->getDescription())->toBe('Publish the laranail/polyglot configuration.')
        ->and($command->isHidden())->toBeFalse();
});

it('takes no options or arguments of its own', function (): void {
    $definition = polyglotInstallCommand()->getNativeDefinition();

    expect($definition->getOptions())->toBe([])
        ->and($definition->getArguments())->toBe([]);
});

it('publishes the config and lists the next steps', function (): void {
    $this->artisan('laranail::polyglot.install')
        ->expectsOutputToContain('Published config/laranail/polyglot.php.')
        ->expectsOutputToContain('Next')
        ->expectsOutputToContain('Point laranail.polyglot.services.* at your services')
        ->expectsOutputToContain('php artisan laranail::polyglot.doctor')
        ->expectsOutputToContain('laranail.polyglot.process.enabled is true')
        ->expectsOutputToContain('laranail.polyglot.callbacks.enabled is true')
        ->assertExitCode(0);
});

it('extends the package-tools install base and keeps both console traits', function (): void {
    expect(is_subclass_of(InstallCommand::class, PackageToolsInstallCommand::class))->toBeTrue();

    $traits = class_uses(InstallCommand::class);

    expect($traits)->toHaveKey(InteractsWithConsoleServices::class)
        ->and($traits)->toHaveKey(InteractsWithConsoleWriter::class);
});
