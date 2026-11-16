<?php

namespace App\Actions;

use App\Enums\GenerationStatus;
use App\Enums\SubmissionState;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateGeneration
{
    public function __construct(private PixelforgeClient $client, private GenerationPricing $pricing) {}

    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images: list<string>}  $input
     */
    public function handle(User $user, array $input, string $requestId, string $quote): Generation
    {
        $hash = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        $existing = Generation::query()->where('user_id', $user->id)->where('client_request_id', $requestId)->first();

        if ($existing) {
            abort_if($existing->request_hash !== $hash, 409, 'This submission ID has already been used with different settings.');

            return $existing;
        }

        abort_unless($this->client->configured(), 503, 'Image generation is temporarily unavailable.');

        try {
            $estimate = json_decode(Crypt::decryptString($quote), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages(['quote' => 'Please request a new estimate.']);
        }

        if ($estimate['user_id'] !== $user->id || $estimate['request_hash'] !== $hash || $estimate['expires_at'] < now()->timestamp) {
            throw ValidationException::withMessages(['quote' => 'This estimate has expired or the settings changed. Please refresh it.']);
        }

        $current = $this->pricing->estimate($input, $this->client->pricing());
        if ($current !== $estimate['estimate']) {
            throw ValidationException::withMessages(['quote' => 'Pricing has changed. Please request a new estimate.']);
        }

        return DB::transaction(function () use ($user, $requestId, $hash, $input, $current): Generation {
            User::query()->lockForUpdate()->findOrFail($user->id);
            $existing = Generation::query()->where('user_id', $user->id)->where('client_request_id', $requestId)->first();
            if ($existing) {
                abort_if($existing->request_hash !== $hash, 409, 'This submission ID has already been used with different settings.');

                return $existing;
            }

            $id = (string) Str::uuid();
            $generation = new Generation;
            $generation->id = $id;
            $generation->fill([
                'user_id' => $user->id,
                'client_request_id' => $requestId,
                'request_hash' => $hash,
                'request_payload' => [...$input, 'metadata' => ['local_generation_id' => $id]],
                'status' => GenerationStatus::Queued,
                'submission_state' => SubmissionState::Pending,
                'estimated_provider_cost' => $current['provider_cost'],
                'estimated_user_cost' => $current['user_charge'],
                'estimate_snapshot' => $current,
                'next_attempt_at' => now(),
            ])->save();

            return $generation;
        }, 3);
    }
}
