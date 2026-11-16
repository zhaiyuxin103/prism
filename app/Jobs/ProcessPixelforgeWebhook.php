<?php

namespace App\Jobs;

use App\Actions\ApplyGenerationSnapshot;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ProcessPixelforgeWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public int $uniqueFor = 60;

    public int $backoff = 15;

    public function __construct(public int $eventId) {}

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public function handle(ApplyGenerationSnapshot $apply): void
    {
        try {
            DB::transaction(function () use ($apply): void {
                $event = WebhookEvent::query()->lockForUpdate()->findOrFail($this->eventId);
                if ($event->processing_status !== 'pending') {
                    return;
                }

                if ($event->event_type !== 'ping') {
                    $snapshot = $event->payload['data']['object'];
                    if ($event->event_type !== 'generation.'.($snapshot['status'] ?? '')) {
                        throw new RuntimeException('Event type does not match generation status.');
                    }
                    $generation = Generation::query()->where('provider_generation_id', $event->provider_generation_id)->first();
                    $localId = $snapshot['metadata']['local_generation_id'] ?? null;
                    if ($generation === null && is_string($localId)) {
                        $generation = Generation::query()->find($localId);
                    }
                    if (! $generation) {
                        throw new RuntimeException('No matching local generation.');
                    }
                    $apply->handle($generation->id, $snapshot);
                }

                $event->update(['processing_status' => 'processed', 'processed_at' => now(), 'next_attempt_at' => null, 'last_error' => null]);
            }, 3);
        } catch (Throwable $exception) {
            report($exception);
            DB::transaction(function () use ($exception): void {
                $event = WebhookEvent::query()->lockForUpdate()->findOrFail($this->eventId);
                if ($event->processing_status !== 'pending') {
                    return;
                }
                $attempts = $event->attempts + 1;
                $event->update([
                    'attempts' => $attempts,
                    'processing_status' => $attempts >= 5 ? 'needs_review' : 'pending',
                    'next_attempt_at' => $attempts >= 5 ? null : now()->addSeconds(30 * $attempts),
                    'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                ]);
            }, 3);
        }
    }
}
