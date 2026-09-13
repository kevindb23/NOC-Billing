<?php

namespace App\Jobs;

use App\Models\RouterOperation;
use App\Services\NetworkAutomationClient;
use App\Services\NetworkAutomationException;
use App\Services\RouterOperationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ExecuteRouterOperation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $operationId) {}

    public function handle(NetworkAutomationClient $client, RouterOperationService $operations): void
    {
        $operation = RouterOperation::query()->findOrFail($this->operationId);
        if (in_array($operation->status, [RouterOperation::STATUS_SUCCEEDED, RouterOperation::STATUS_FAILED, RouterOperation::STATUS_CANCELLED], true)) {
            return;
        }

        $started = Carbon::now();
        $operation->forceFill([
            'status' => RouterOperation::STATUS_RUNNING,
            'started_at' => $started,
            'error_code' => null,
            'error_message' => null,
        ])->save();

        try {
            $result = $client->execute($operations->gatewayPayload($operation));
            $succeeded = in_array($result['status'], ['succeeded', 'connected'], true);
            $finished = Carbon::now();
            $operation->forceFill([
                'status' => $succeeded ? RouterOperation::STATUS_SUCCEEDED : RouterOperation::STATUS_FAILED,
                'result' => $result,
                'error_code' => $succeeded ? null : ($result['status'] === 'unsupported' ? 'unsupported_operation' : 'operation_failed'),
                'error_message' => $succeeded ? null : $result['message'],
                'finished_at' => $finished,
                'duration_ms' => max(0, $started->diffInMilliseconds($finished)),
            ])->save();

            if ($succeeded) {
                $operation->router()->update(['last_contact_at' => $finished]);
            }
        } catch (NetworkAutomationException $exception) {
            $this->markFailed($operation, $started, $exception->errorCode, $exception->getMessage());
        } catch (\Throwable) {
            $this->markFailed($operation, $started, 'automation_operation_failed', 'The router operation could not be completed.');
        }
    }

    private function markFailed(RouterOperation $operation, Carbon $started, string $code, string $message): void
    {
        $finished = Carbon::now();
        $operation->forceFill([
            'status' => RouterOperation::STATUS_FAILED,
            'error_code' => $code,
            'error_message' => $message,
            'finished_at' => $finished,
            'duration_ms' => max(0, $started->diffInMilliseconds($finished)),
        ])->save();
    }
}
