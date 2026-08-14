<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Events;

use Simtabi\Laranail\Polyglot\ValueObjects\Call;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;

final readonly class CallSucceeded
{
    public function __construct(public Call $call, public CallResult $result) {}
}
