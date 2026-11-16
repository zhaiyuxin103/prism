<?php

namespace App\Http\Requests;

use App\Actions\CreditLedger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseCreditsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasVerifiedEmail() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', Rule::in(CreditLedger::PACKAGES)],
            'client_request_id' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['amount.in' => 'Choose a credit package of $10, $25 or $50.'];
    }
}
