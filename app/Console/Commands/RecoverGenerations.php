<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPixelforgeWebhook;
use App\Jobs\SyncGeneration;
use App\Models\Generation;
use App\Models\WebhookEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Recover pending submissions, missed webhooks, and unfinished generations')]
#[Signature('generations:recover')]
class RecoverGenerations extends Command
{
    public function handle(): int
    {
        $generations = Generation::query()->whereIn('status', ['queued', 'processing'])
            ->where('next_attempt_at', '<=', now())->oldest('next_attempt_at')->limit(100)->get();
        foreach ($generations as $generation) {
            SyncGeneration::dispatch($generation->id);
        }

        $events = WebhookEvent::query()->where('processing_status', 'pending')
            ->where('next_attempt_at', '<=', now())->oldest('next_attempt_at')->limit(100)->get();
        foreach ($events as $event) {
            ProcessPixelforgeWebhook::dispatch($event->id);
        }

        $this->info("Queued {$generations->count()} generation checks and {$events->count()} webhook events.");

        return self::SUCCESS;
    }
}
