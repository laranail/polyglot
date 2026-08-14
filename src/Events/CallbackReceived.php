<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Events;

use Simtabi\Laranail\Polyglot\Callbacks\CallbackEnvelope;

final readonly class CallbackReceived
{
    public function __construct(public CallbackEnvelope $envelope) {}
}
