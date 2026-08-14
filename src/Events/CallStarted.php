<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Events;

use Simtabi\Laranail\Polyglot\ValueObjects\Call;

final readonly class CallStarted
{
    public function __construct(public Call $call) {}
}
