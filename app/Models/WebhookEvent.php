<?php

namespace App\Models;

use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider_event_id
 * @property string $event_type
 * @property string|null $provider_generation_id
 * @property array<string, mixed> $payload
 * @property string $processing_status
 * @property int $attempts
 * @property Carbon|null $next_attempt_at
 */
class WebhookEvent extends Model
{
    /** @use HasFactory<WebhookEventFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['processing_status' => 'pending', 'attempts' => 0];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }
}
