<?php

namespace App\Models;

use App\Enums\GenerationStatus;
use App\Enums\SubmissionState;
use Database\Factories\GenerationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string $client_request_id
 * @property string $request_hash
 * @property array{prompt: string, size: string, quality: string, reference_images: list<string>, metadata: array{local_generation_id: string}} $request_payload
 * @property string|null $provider_generation_id
 * @property SubmissionState $submission_state
 * @property GenerationStatus $status
 * @property string $estimated_provider_cost
 * @property string $estimated_user_cost
 * @property array<string, mixed> $estimate_snapshot
 * @property array<string, string>|null $provider_rates
 * @property array<string, int>|null $usage
 * @property string|null $image_url
 * @property Carbon|null $image_url_expires_at
 * @property Carbon|null $provider_created_at
 * @property Carbon|null $provider_started_at
 * @property Carbon|null $provider_completed_at
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $submit_attempts
 * @property int $poll_attempts
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $last_checked_at
 * @property Carbon|null $capacity_wait_started_at
 * @property Carbon $created_at
 * @property-read GenerationSettlement|null $settlement
 */
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $guarded = ['id'];

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
