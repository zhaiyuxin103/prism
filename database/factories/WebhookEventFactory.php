<?php

namespace Database\Factories;

use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WebhookEvent> */
class WebhookEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $id = 'evt_'.Str::ulid();

        return [
            'provider_event_id' => $id,
            'event_type' => 'ping',
            'payload' => ['id' => $id, 'type' => 'ping'],
            'received_at' => now(),
            'next_attempt_at' => now(),
        ];
    }
}
