<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPixelforgeWebhook;
use App\Jobs\SyncGeneration;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Console\Command;

class RecoverGenerations extends Command
{
    protected $signature = 'generations:recover';

    protected $description = 'Recover pending submissions, missed webhooks, and unfinished generations';

    public function handle(): int
    {
        $generations = Generation::query()->whereIn('status', ['queued', 'processing'])
            ->where('next_attempt_at', '<=', now())->orderBy('next_attempt_at')->limit(100)->get();
        foreach ($generations as $generation) {
            SyncGeneration::dispatch($generation->id);
        }

        $events = WebhookEvent::query()->where('processing_status', 'pending')
            ->where('next_attempt_at', '<=', now())->orderBy('next_attempt_at')->limit(100)->get();
        foreach ($events as $event) {
            ProcessPixelforgeWebhook::dispatch($event->id);
        }

        $this->info("Queued {$generations->count()} generation checks and {$events->count()} webhook events.");

        return self::SUCCESS;
    }
}
