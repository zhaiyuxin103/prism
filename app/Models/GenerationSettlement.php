<?php

namespace App\Models;

use Database\Factories\GenerationSettlementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $provider_cost
 * @property string $user_charge
 * @property string $outcome
 *
 * @mixin IdeHelperGenerationSettlement
 */
class GenerationSettlement extends Model
{
    /** @use HasFactory<GenerationSettlementFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

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
