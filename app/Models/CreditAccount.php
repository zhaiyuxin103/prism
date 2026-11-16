<?php

namespace App\Models;

use App\Actions\GenerationPricing;
use Database\Factories\CreditAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property numeric-string $balance
 * @property numeric-string $reserved
 *
 * @mixin IdeHelperCreditAccount
 */
#[Guarded(['id'])]
class CreditAccount extends Model
{
    /** @use HasFactory<CreditAccountFactory> */
    use HasFactory;

    /** @var array<string, string> */
    protected $attributes = ['balance' => GenerationPricing::ZERO, 'reserved' => GenerationPricing::ZERO];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['balance' => 'string', 'reserved' => 'string'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CreditEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(CreditEntry::class);
    }

    /** @return numeric-string */
    public function available(): string
    {
        return bcsub($this->balance, $this->reserved, GenerationPricing::PRECISION);
    }
}
