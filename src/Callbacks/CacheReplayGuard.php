<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Callbacks;

use Simtabi\Laranail\Polyglot\Contracts\ReplayGuard;
use Simtabi\Laranail\Polyglot\Support\PolyglotConfig;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

/**
 * Claims a delivery id in the cache, atomically.
 *
 * `add()` rather than `has()` + `put()`: the read-then-write pair has a window
 * where two concurrent deliveries of the same id both see "not seen" and both
 * proceed, which is exactly the case a replay guard exists to stop. `add()` is
 * a single atomic operation on every store that matters.
 */
final readonly class CacheReplayGuard implements ReplayGuard
{
    public function __construct(
        private CacheFactory $cache,
        private PolyglotConfig $config,
    ) {}

    public function claim(string $id, int $ttlSeconds): bool
    {
        return $this->cache
            ->store($this->config->stringOrNull('callbacks.replay_store'))
            ->add($this->key($id), true, $ttlSeconds);
    }

    private function key(string $id): string
    {
        return 'laranail:polyglot:callback:' . hash('sha256', $id);
    }
}
