<?php

namespace App\Jobs;

use App\Actions\ApplyGenerationSnapshot;
use App\Actions\PixelforgeClient;
use App\Enums\SubmissionState;
use App\Exceptions\PixelforgeException;
use App\Models\Generation;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncGeneration implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 90;

    public int $backoff = 30;

    public function __construct(public string $generationId) {}

    public function uniqueId(): string
    {
        return $this->generationId;
    }

    public function handle(PixelforgeClient $client, ApplyGenerationSnapshot $apply): void
    {
        $lock = Cache::lock('pixelforge:sync:'.$this->generationId, 60);
        if (! $lock->get()) {
            return;
        }

        $scheduleNextAttempt = false;
        try {
            $generation = Generation::query()->find($this->generationId);
            if (! $generation || $generation->status->isTerminal() || $generation->next_attempt_at === null) {
                return;
            }
            if ($generation->next_attempt_at->isFuture()) {
                $scheduleNextAttempt = true;

                return;
            }

            if ($generation->capacity_wait_started_at?->copy()->addSeconds((int) config('services.pixelforge.capacity_wait_seconds'))->lessThanOrEqualTo(now())) {
                $this->defer('capacity_wait_expired', 'Capacity is still unavailable. Automatic checks paused. Resume checking to try again.', null);

                return;
            }

            $submitting = $generation->provider_generation_id === null;
            $attemptField = $submitting ? 'submit_attempts' : 'poll_attempts';
            $limit = (int) config('services.pixelforge.'.$attemptField);
            if ($limit <= $generation->$attemptField) {
                $this->defer('recovery_paused', 'Automatic checks paused. Resume checking to recover the original request.', null);

                return;
            }

            $wasUncertain = $generation->submission_state === SubmissionState::Uncertain;
            $attempt = $generation->$attemptField + 1;

            $claimed = DB::transaction(function () use ($attemptField, $attempt): bool {
                $current = Generation::query()->lockForUpdate()->findOrFail($this->generationId);
                if ($current->status->isTerminal()) {
                    return false;
                }
                $current->update([
                    $attemptField => $attempt,
                    'submission_state' => $current->provider_generation_id === null ? SubmissionState::Uncertain : SubmissionState::Accepted,
                    'next_attempt_at' => now()->addMinute(),
                ]);

                return true;
            }, 3);

            if (! $claimed) {
                return;
            }
            $generation->refresh();
            $submitting = $generation->provider_generation_id === null;
            $scheduleNextAttempt = true;

            try {
                $snapshot = $submitting ? $client->submit($generation) : $client->retrieve($generation->provider_generation_id);
                $apply->handle($generation->id, $snapshot);
            } catch (PixelforgeException $exception) {
                if (in_array($exception->errorCode, ['local_rate_limit', 'rate_limit_exceeded', 'concurrency_limit_exceeded'], true)) {
                    $this->waitForCapacity($exception, $attemptField, $wasUncertain);

                    return;
                }
                if ($submitting && $exception->definitiveRejection && ! $wasUncertain) {
                    if (! $exception->retryable || $attempt >= $limit) {
                        $apply->reject($generation->id, $exception->errorCode, $exception->retryable
                            ? 'The provider remained unavailable after repeated attempts. No image was created. Please try again later.'
                            : $exception->getMessage());

                        return;
                    }
                    $this->defer($exception->errorCode, $exception->getMessage(), max($exception->retryAfter, min(300, 5 * 2 ** ($attempt - 1))), SubmissionState::Pending);

                    return;
                }

                $this->defer($exception->errorCode, $attempt >= $limit
                    ? 'We could not confirm the result. Resume checking the original request; do not submit it again.'
                    : $exception->getMessage(), $attempt >= $limit ? null : max($exception->retryAfter, min(300, 15 * (1 << min($attempt - 1, 5)))));
            } catch (ConnectionException) {
                $this->defer('connection_error', 'Connection interrupted. We are checking the original request.', $attempt >= $limit ? null : min(300, 15 * (1 << min($attempt - 1, 5))));
            } catch (Throwable $exception) {
                report($exception);
                $this->defer('invalid_provider_result', 'The result needs verification. We are checking with the provider.', $attempt >= $limit ? null : 60);
            }
        } finally {
            $lock->release();
            if ($scheduleNextAttempt && config('queue.connections.'.($this->connection ?? config('queue.default')).'.driver') !== 'sync') {
                $generation = Generation::query()->find($this->generationId);
                if ($generation && ! $generation->status->isTerminal() && $generation->next_attempt_at !== null) {
                    self::dispatch($generation->id)
                        ->onConnection($this->connection)
                        ->onQueue($this->queue)
                        ->delay($generation->next_attempt_at)
                        ->afterCommit();
                }
            }
        }
    }

    private function waitForCapacity(PixelforgeException $exception, string $attemptField, bool $wasUncertain): void
    {
        DB::transaction(function () use ($exception, $attemptField, $wasUncertain): void {
            $generation = Generation::query()->lockForUpdate()->findOrFail($this->generationId);
            if ($generation->status->isTerminal()) {
                return;
            }
            $startedAt = $generation->capacity_wait_started_at ?? now();
            $waitingSeconds = max(0, now()->getTimestamp() - $startedAt->getTimestamp());
            $delay = $this->capacityRetryDelay($exception, $waitingSeconds);
            $delay = min($delay, max(0, (int) config('services.pixelforge.capacity_wait_seconds') - $waitingSeconds));
            $generation->fill([
                $attemptField => max(0, $generation->$attemptField - 1),
                'capacity_wait_started_at' => $startedAt,
                'error_code' => $exception->errorCode,
                'error_message' => $exception->getMessage(),
                'last_checked_at' => now(),
                'next_attempt_at' => now()->addSeconds($delay),
            ]);
            if (! $wasUncertain && $generation->submission_state !== SubmissionState::Accepted) {
                $generation->submission_state = SubmissionState::Pending;
            }
            $generation->save();
        }, 3);
    }

    private function capacityRetryDelay(PixelforgeException $exception, int $waitingSeconds): int
    {
        if ($exception->errorCode === 'concurrency_limit_exceeded') {
            return max($exception->retryAfter, 5 + random_int(0, 3));
        }

        return max($exception->retryAfter, min(300, 15 * (1 << min(intdiv($waitingSeconds, 30), 5))));
    }

    private function defer(string $code, string $message, ?int $delay, ?SubmissionState $state = null): void
    {
        DB::transaction(function () use ($code, $message, $delay, $state): void {
            $generation = Generation::query()->lockForUpdate()->findOrFail($this->generationId);
            if ($generation->status->isTerminal()) {
                return;
            }
            $generation->fill([
                'error_code' => $code,
                'error_message' => $message,
                'last_checked_at' => now(),
                'next_attempt_at' => $delay === null ? null : now()->addSeconds($delay),
            ]);
            if ($state !== null && $generation->submission_state !== SubmissionState::Accepted) {
                $generation->submission_state = $state;
            }
            $generation->save();
        }, 3);
    }
}
