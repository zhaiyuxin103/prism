<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\SubmissionState;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Generation> */
class GenerationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $id = (string) Str::uuid();
        $input = ['prompt' => 'A quiet mountain lake', 'size' => '1024x1024', 'quality' => 'medium', 'reference_images' => []];

        return [
            'id' => $id,
            'user_id' => User::factory(),
            'client_request_id' => (string) Str::uuid(),
            'request_hash' => hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)),
            'request_payload' => [...$input, 'metadata' => ['local_generation_id' => $id]],
            'status' => GenerationStatus::Queued,
            'submission_state' => SubmissionState::Pending,
            'estimated_provider_cost' => '0.057780000000000000',
            'estimated_user_cost' => '0.072225000000000000',
            'estimate_snapshot' => [],
            'next_attempt_at' => now(),
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'provider_generation_id' => 'gen_'.Str::ulid(),
            'submission_state' => SubmissionState::Accepted,
        ]);
    }

    public function uncertain(): static
    {
        return $this->state(fn (): array => ['submission_state' => SubmissionState::Uncertain]);
    }
}
