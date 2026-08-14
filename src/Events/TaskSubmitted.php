<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Events;

use Simtabi\Laranail\Polyglot\Tasks\TaskHandle;

final readonly class TaskSubmitted
{
    public function __construct(public TaskHandle $handle) {}
}
