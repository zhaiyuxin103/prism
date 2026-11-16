<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerationInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:4000'],
            'size' => ['required', Rule::in(['1024x1024', '1536x1024', '1024x1536'])],
            'quality' => ['required', Rule::in(['low', 'medium', 'high'])],
            'reference_images' => ['present', 'array', 'max:4'],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /** @return array{prompt: string, size: string, quality: string, reference_images: list<string>} */
    public function generationInput(): array
    {
        return [
            'prompt' => $this->validated('prompt'),
            'size' => $this->validated('size'),
            'quality' => $this->validated('quality'),
            'reference_images' => array_values($this->validated('reference_images')),
        ];
    }
}
