<?php

namespace Database\Seeders;

use App\Actions\CreditLedger;
use App\Models\User;
use Illuminate\Database\Seeder;

class CreditEntrySeeder extends Seeder
{
    public function run(CreditLedger $ledger): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $user = User::query()->where('email', 'test@example.com')->first();
        if ($user !== null) {
            $ledger->purchase($user, '10', '96f755e6-ff53-48a8-a4f1-e477d7aa8e9b');
        }
    }
}
