<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CreditAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $user = User::query()->where('email', 'test@example.com')->first();
        $user?->creditAccount()->firstOrCreate();
    }
}
