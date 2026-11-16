<?php

namespace App\Models;

use Database\Factories\WebhookEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $payload
 *
 * @mixin IdeHelperWebhookEvent
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
