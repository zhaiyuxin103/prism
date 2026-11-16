<?php

namespace App\Models;

use App\Enums\CreditEntryType;
use Database\Factories\CreditEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperCreditEntry
 */
#[Guarded(['id'])]
class CreditEntry extends Model
{
    /** @use HasFactory<CreditEntryFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => CreditEntryType::class, 'amount' => 'string', 'reserved_amount' => 'string', 'balance_after' => 'string', 'reserved_after' => 'string'];
    }

    /** @return BelongsTo<CreditAccount, $this> */
    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(CreditAccount::class);
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
