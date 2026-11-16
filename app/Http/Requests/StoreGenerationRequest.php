<?php

namespace App\Http\Requests;

class StoreGenerationRequest extends GenerationInputRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'client_request_id' => ['required', 'uuid'],
            'quote' => ['required', 'string', 'max:20000'],
        ];
    }
}
