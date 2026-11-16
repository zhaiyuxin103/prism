<?php

namespace Database\Factories;

use App\Actions\GenerationPricing;
use App\Enums\CreditEntryType;
use App\Models\CreditAccount;
use App\Models\CreditEntry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CreditEntry> */
class CreditEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'credit_account_id' => CreditAccount::factory()->state(['balance' => '10.000000000000000000']),
            'reference' => 'purchase:'.Str::uuid(), 'type' => CreditEntryType::Purchase,
            'amount' => '10.000000000000000000', 'reserved_amount' => GenerationPricing::ZERO,
            'balance_after' => '10.000000000000000000', 'reserved_after' => GenerationPricing::ZERO,
        ];
    }
}
