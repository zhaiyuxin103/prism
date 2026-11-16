<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
use App\Enums\SubmissionState;
use App\Models\Generation;
use App\Models\GenerationSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GenerationSettlement> */
class GenerationSettlementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'generation_id' => Generation::factory()->state([
                'status' => GenerationStatus::Failed,
                'submission_state' => SubmissionState::Rejected,
                'next_attempt_at' => null,
            ]),
            'outcome' => 'failed',
            'provider_cost' => '0.000000000000000000',
            'user_charge' => '0.000000000000000000',
            'calculation_version' => 'v2',
            'settled_at' => now(),
        ];
    }
}
