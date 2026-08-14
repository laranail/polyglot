<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Testing;

use Simtabi\Laranail\Polyglot\ValueObjects\Call;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;

/**
 * One call a fake intercepted, and what it answered.
 */
final readonly class RecordedCall
{
    public function __construct(
        public Call $call,
        public CallResult $result,
    ) {}
}
