<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Polyglot\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Events\Dispatcher;
use Simtabi\Laranail\Polyglot\Enums\TaskStatus;
use Simtabi\Laranail\Polyglot\Tasks\TaskHandle;
use Simtabi\Laranail\Polyglot\Contracts\TaskStore;
use Simtabi\Laranail\Polyglot\Events\CallbackReceived;
use Simtabi\Laranail\Polyglot\ValueObjects\CallResult;
use Simtabi\Laranail\Polyglot\Callbacks\CallbackEnvelope;

/**
 * Receives a verified callback and records the task's outcome.
 *
 * By the time this runs the middleware has already proved the delivery is
 * authentic, inside the timestamp window, and not a replay — so there is
 * nothing left to check here, and nothing that can be forgotten.
 */
final readonly class CallbackController
{
    public function __construct(
        private TaskStore $tasks,
        private Dispatcher $events,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $envelope = $request->attributes->get('polyglot_callback');

        if (! $envelope instanceof CallbackEnvelope) {
            // Unreachable through the registered route; a 401 rather than a 500
            // if someone wires the controller up without the middleware.
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($envelope->taskId !== null) {
            $handle = $this->tasks->find($envelope->taskId);

            if ($handle instanceof TaskHandle) {
                $result = $envelope->status->isFinished()
                    ? new CallResult(
                        ok: $envelope->status === TaskStatus::Succeeded,
                        data: $envelope->payload,
                    )
                    : null;

                $this->tasks->put($handle->withStatus($envelope->status), $result);
            }
        }

        $this->events->dispatch(new CallbackReceived($envelope));

        return response()->json(['received' => true]);
    }
}
