<?php

namespace App\Http\Resources;

use App\Models\Generation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Generation */
class GenerationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'prompt' => $this->request_payload['prompt'],
            'size' => $this->request_payload['size'],
            'quality' => $this->request_payload['quality'],
            'reference_images' => $this->request_payload['reference_images'],
            'status' => $this->status->value,
            'submission_state' => $this->submission_state->value,
            'estimated_cost' => $this->estimated_user_cost,
            'actual_cost' => $this->settlement?->user_charge,
            'image_url' => $this->image_url,
            'image_expired' => $this->image_url_expires_at?->isPast() ?? false,
            'error_message' => $this->error_message,
            'can_resume' => ! $this->status->isTerminal() && $this->next_attempt_at === null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
