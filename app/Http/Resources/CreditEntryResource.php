<?php

namespace App\Http\Resources;

use App\Models\CreditEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CreditEntry */
class CreditEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'amount' => $this->amount,
            'reserved_amount' => $this->reserved_amount,
            'balance_after' => $this->balance_after,
            'reserved_after' => $this->reserved_after,
            'generation_id' => $this->generation_id,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
