<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Contracts;

use Simtabi\Laranail\Polyglot\Tasks\TaskHandle;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;

/**
 * Where submitted tasks and their results live between requests.
 */
interface TaskStore
{
    public function put(TaskHandle $handle, ?CallResult $result = null): void;

    public function find(string $id): ?TaskHandle;

    public function result(string $id): ?CallResult;

    public function forget(string $id): void;
}
