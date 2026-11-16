<?php

namespace App\Models;

use Database\Factories\GenerationSettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperGenerationSettlement
 */
#[Guarded(['id'])]
class GenerationSettlement extends Model
{
    /** @use HasFactory<GenerationSettlementFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['provider_cost' => 'string', 'user_charge' => 'string', 'settled_at' => 'datetime'];
    }

    /** @return BelongsTo<Generation, $this> */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }
}
