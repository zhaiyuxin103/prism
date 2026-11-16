<?php

namespace App\Actions;

use App\Enums\CreditEntryType;
use App\Models\CreditAccount;
use App\Models\CreditEntry;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreditLedger
{
    public const array PACKAGES = ['10', '25', '50'];

    public function purchase(User $user, string $amount, string $requestId): CreditEntry
    {
        if (! in_array($amount, self::PACKAGES, true)) {
            throw ValidationException::withMessages(['amount' => 'Choose a credit package of $10, $25 or $50.']);
        }

        return DB::transaction(function () use ($user, $amount, $requestId): CreditEntry {
            $account = $this->lockAccount($user);
            $reference = 'purchase:'.$requestId;
            $existing = $account->entries()->where('reference', $reference)->first();
            if ($existing) {
                abort_unless($existing->amount === bcadd($amount, '0', GenerationPricing::PRECISION), 409, 'This purchase ID has already been used for a different amount.');

                return $existing;
            }

            return $this->record($account, CreditEntryType::Purchase, $reference, $amount, GenerationPricing::ZERO);
        }, 3);
    }

    /**
     * Called inside the generation creation transaction, after locking its user.
     *
     * @param  numeric-string  $amount
     */
    public function reserve(User $user, Generation $generation, string $amount): void
    {
        $account = $this->lockAccount($user);
        if (bccomp($account->available(), $amount, GenerationPricing::PRECISION) < 0) {
            throw ValidationException::withMessages(['balance' => 'Not enough available credits. Buy credits to cover the upper estimate.']);
        }

        $this->record($account, CreditEntryType::Reservation, 'reserve:'.$generation->id, GenerationPricing::ZERO, $amount, $generation->id);
        $generation->update(['credit_account_id' => $account->id, 'reserved_user_cost' => $amount]);
    }

    /**
     * Called inside the settlement transaction, with the generation locked.
     *
     * @param  numeric-string  $charge
     */
    public function settle(Generation $generation, string $charge): void
    {
        if ($generation->credit_account_id === null) {
            return;
        }

        $account = CreditAccount::query()->lockForUpdate()->findOrFail($generation->credit_account_id);
        $reserved = $generation->reserved_user_cost;
        throw_if($reserved === null || bccomp($account->reserved, $reserved, GenerationPricing::PRECISION) < 0, RuntimeException::class, 'Generation credit reservation is inconsistent.');

        $this->record($account, CreditEntryType::Settlement, 'settle:'.$generation->id,
            bcsub('0', $charge, GenerationPricing::PRECISION),
            bcsub('0', $reserved, GenerationPricing::PRECISION), $generation->id);
    }

    /** @return array{balance: numeric-string, reserved: numeric-string, available: numeric-string, has_debt: bool} */
    public function balance(User $user): array
    {
        $account = CreditAccount::query()->where('user_id', $user->id)->first();

        return [
            'balance' => $account->balance ?? GenerationPricing::ZERO,
            'reserved' => $account->reserved ?? GenerationPricing::ZERO,
            'available' => $account?->available() ?? GenerationPricing::ZERO,
            'has_debt' => $account !== null && bccomp($account->balance, '0', GenerationPricing::PRECISION) < 0,
        ];
    }

    private function lockAccount(User $user): CreditAccount
    {
        User::query()->lockForUpdate()->findOrFail($user->id);

        return CreditAccount::query()->where('user_id', $user->id)->lockForUpdate()->firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * @param  numeric-string  $amount
     * @param  numeric-string  $reservedAmount
     */
    private function record(CreditAccount $account, CreditEntryType $type, string $reference, string $amount, string $reservedAmount, ?string $generationId = null): CreditEntry
    {
        $balance = bcadd($account->balance, $amount, GenerationPricing::PRECISION);
        $reserved = bcadd($account->reserved, $reservedAmount, GenerationPricing::PRECISION);
        $entry = $account->entries()->create([
            'generation_id' => $generationId, 'type' => $type, 'reference' => $reference,
            'amount' => bcadd($amount, '0', GenerationPricing::PRECISION),
            'reserved_amount' => bcadd($reservedAmount, '0', GenerationPricing::PRECISION),
            'balance_after' => $balance, 'reserved_after' => $reserved,
        ]);
        $account->update(['balance' => $balance, 'reserved' => $reserved]);

        return $entry;
    }
}
