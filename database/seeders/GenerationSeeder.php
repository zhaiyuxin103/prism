<?php

namespace Database\Seeders;

use App\Models\Generation;
use App\Models\GenerationSettlement;
use App\Models\User;
use Illuminate\Database\Seeder;

class GenerationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $user = User::query()->first();
        if ($user === null) {
            return;
        }

        GenerationSettlement::factory()->for(Generation::factory()->for($user)->state([
            'status' => 'failed',
            'submission_state' => 'rejected',
            'next_attempt_at' => null,
            'error_message' => 'Local UI example: this generation was rejected and no charge was recorded.',
        ]))->create();
    }
}
