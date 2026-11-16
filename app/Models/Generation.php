<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\SubmissionState;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property array{prompt: string, size: string, quality: string, reference_images: list<string>, metadata: array{local_generation_id: string}} $request_payload
 * @property array<string, mixed> $estimate_snapshot
 * @property array<string, string>|null $provider_rates
 * @property array<string, int>|null $usage
 *
 * @mixin IdeHelperGeneration
 */
#[Guarded(['id'])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'estimate_snapshot' => 'array',
            'provider_rates' => 'array',
            'usage' => 'array',
            'status' => GenerationStatus::class,
            'submission_state' => SubmissionState::class,
            'estimated_provider_cost' => 'string',
            'estimated_user_cost' => 'string',
            'submit_attempts' => 'integer',
            'poll_attempts' => 'integer',
            'image_url_expires_at' => 'datetime',
            'provider_created_at' => 'datetime',
            'provider_started_at' => 'datetime',
            'provider_completed_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'capacity_wait_started_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<GenerationSettlement, $this> */
    public function settlement(): HasOne
    {
        return $this->hasOne(GenerationSettlement::class);
    }
}
