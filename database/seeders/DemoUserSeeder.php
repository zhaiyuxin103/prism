<?php

namespace Database\Seeders;

use App\Actions\GenerationPricing;
use App\Actions\Teams\CreateTeam;
use App\Enums\CreditEntryType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CreateTeam $createTeam): void
    {
        if (! app()->environment(config('login-link.allowed_environments'))) {
            return;
        }

        DB::transaction(function () use ($createTeam): void {
            foreach (config('login-link.demo_users') as $email => $profile) {
                $user = User::query()->where('email', $email)->first();
                if ($user !== null) {
                    continue;
                }

                $user = new User;
                $user->forceFill([
                    'name' => $profile['name'], 'email' => $email,
                    'password' => Str::random(64), 'email_verified_at' => now(),
                    'is_admin' => $profile['is_admin'],
                ])->save();
                $createTeam->handle($user, $user->name."'s Team", isPersonal: true);

                $balance = bcadd($profile['balance'], '0', GenerationPricing::PRECISION);
                $account = $user->creditAccount()->create(['balance' => $balance]);
                if (bccomp($balance, '0', GenerationPricing::PRECISION) > 0) {
                    $account->entries()->create([
                        'type' => CreditEntryType::Purchase, 'reference' => 'demo:opening-balance',
                        'amount' => $balance, 'reserved_amount' => GenerationPricing::ZERO,
                        'balance_after' => $balance, 'reserved_after' => GenerationPricing::ZERO,
                    ]);
                }
            }
        });
    }
}
