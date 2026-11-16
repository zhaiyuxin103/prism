<?php

namespace Database\Seeders;

use App\Actions\DemoLogin;
use App\Actions\GenerationPricing;
use App\Actions\Teams\CreateTeam;
use App\Enums\CreditEntryType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(DemoLogin $demoLogin, CreateTeam $createTeam): void
    {
        if (! $demoLogin->enabled()) {
            return;
        }

        DB::transaction(function () use ($createTeam): void {
            foreach (DemoLogin::ACCOUNTS as $profile) {
                $user = User::query()->where('email', $profile['email'])->first();
                if ($user !== null) {
                    throw_unless($user->is_demo, RuntimeException::class, 'A demo email is already used by a non-demo account.');

                    continue;
                }

                $user = new User;
                $user->forceFill([
                    'name' => $profile['name'], 'email' => $profile['email'],
                    'password' => Str::random(64), 'email_verified_at' => now(),
                    'is_admin' => $profile['is_admin'], 'is_demo' => true,
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
