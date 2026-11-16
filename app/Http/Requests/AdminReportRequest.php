<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AdminReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-admin-report') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'start_date' => CarbonImmutable::now('UTC')->subDays(29)->toDateString(),
            'end_date' => CarbonImmutable::now('UTC')->toDateString(),
        ]);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:'.CarbonImmutable::now('UTC')->toDateString()],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = CarbonImmutable::parse($this->string('start_date')->toString(), 'UTC');
            $end = CarbonImmutable::parse($this->string('end_date')->toString(), 'UTC');
            if ($start->diffInDays($end) > 91) {
                $validator->errors()->add('end_date', 'Choose a date range of 92 days or fewer.');
            }
        }];
    }
}
