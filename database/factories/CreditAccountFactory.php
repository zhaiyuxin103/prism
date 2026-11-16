<?php

namespace Database\Factories;

use App\Actions\GenerationPricing;
use App\Models\CreditAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CreditAccount> */
class CreditAccountFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'balance' => GenerationPricing::ZERO, 'reserved' => GenerationPricing::ZERO];
    }
}
