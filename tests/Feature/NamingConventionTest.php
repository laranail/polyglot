<?php

declare(strict_types=1);

use Simtabi\Laranail\Polyglot\Bridge\PolyglotManager;
use Simtabi\Laranail\Package\Tools\Testing\NamingScope;
use Simtabi\Laranail\Package\Tools\Testing\AssertsRegisteredNames;

uses(AssertsRegisteredNames::class);

/**
 * Container aliases live in one flat map, so a second package claiming `polyglot` would silently
 * replace this one. The scoped `laranail.polyglot` is the package's name; the bare alias stays,
 * deprecated, and is the only bare name allowed. Read from the live container, not the provider source.
 */
function polyglotScope(): NamingScope
{
    // basePath is src/: package-tools v0.1.3 defaults ownership to the package root, which also
    // claims vendor/ and tests/ registrations (fixed in v0.1.4).
    return NamingScope::for('laranail/polyglot', 'Simtabi\\Laranail\\Polyglot\\', basePath: dirname(__DIR__, 2) . '/src');
}

it('scopes its container aliases, with the bare one listed as deprecated', function (): void {
    expect($this->assertContainerAliasesScoped(polyglotScope(), deprecated: ['polyglot']))
        ->toContain('laranail.polyglot');
});

it('resolves the scoped and the deprecated alias to the same manager', function (): void {
    expect(app('laranail.polyglot'))->toBeInstanceOf(PolyglotManager::class)
        ->and(app('polyglot'))->toBe(app('laranail.polyglot'))
        ->and(app(PolyglotManager::class))->toBe(app('laranail.polyglot'));
});
