<?php

namespace App\Actions;

use App\Exceptions\PixelforgeException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class GenerationPricing
{
    public const int PRECISION = 18;

    public const string ZERO = '0.000000000000000000';

    public const array TOKEN_TYPES = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    private const string RATE_PATTERN = '/^\d{1,9}(\.\d{1,9})?$/D';

    /**
     * Exact USD decimal strings; nine rate decimals need at most seventeen after markup.
     *
     * @param  array<string, int>  $usage
     * @param  array<string, string>  $rates
     * @return array{provider_cost: string, user_charge: string}
     */
    public function calculate(array $usage, array $rates): array
    {
        $total = '0';

        foreach (self::TOKEN_TYPES as $type) {
            $tokens = $usage[$type] ?? null;
            $rate = $rates[$type] ?? null;

            throw_if(! is_int($tokens) || $tokens < 0 || ! is_string($rate) || ! is_numeric($rate) || ! preg_match(self::RATE_PATTERN, $rate), InvalidArgumentException::class, 'Incomplete or invalid token usage and rates.');

            $total = bcadd($total, bcmul((string) $tokens, $rate, self::PRECISION), self::PRECISION);
        }

        $provider = bcdiv($total, '1000000', self::PRECISION);

        return [
            'provider_cost' => $provider,
            'user_charge' => bcmul($provider, '1.25', self::PRECISION),
        ];
    }

    /**
     * @param  array{prompt: string, size: string, quality: string, reference_images: list<string>}  $input
     * @param  array<string, mixed>  $pricing
     * @return array{provider_cost: string, user_charge: string, maximum_user_cost: string, usage: array<string, int>, maximum_usage: array<string, int>, rates: array<string, string>, version: string}
     */
    public function estimate(array $input, array $pricing): array
    {
        $rules = [
            'currency' => ['required', 'in:usd'],
            'unit' => ['required', 'in:per_1m_tokens'],
            'rates' => ['required', 'array'],
            'tokens.characters_per_text_token' => ['required', 'integer', 'min:1'],
            'tokens.text_token_variance' => ['required', 'numeric', 'between:0,1', 'regex:/^(?:0(?:\.\d{1,9})?|1(?:\.0{1,9})?)$/D'],
            'tokens.per_reference_image' => ['required', 'integer', 'min:0'],
            'tokens.output.'.$input['quality'].'.'.$input['size'] => ['required', 'integer', 'min:0'],
            'tokens.reasoning.'.$input['quality'].'.min' => ['required', 'integer', 'min:0'],
            'tokens.reasoning.'.$input['quality'].'.max' => ['required', 'integer', 'gte:tokens.reasoning.'.$input['quality'].'.min'],
        ];
        foreach (self::TOKEN_TYPES as $type) {
            $rules['rates.'.$type] = ['required', 'string', 'regex:'.self::RATE_PATTERN];
        }
        $validator = Validator::make($pricing, $rules);
        $variance = data_get($pricing, 'tokens.text_token_variance');
        $variance = is_scalar($variance) ? (string) $variance : '';
        if ($validator->fails() || ! is_numeric($variance)) {
            Log::warning('Invalid Pixelforge pricing response.', ['errors' => $validator->errors()->toArray()]);
            throw new PixelforgeException('invalid_pricing', 'Image pricing is temporarily unavailable. Please try again.');
        }
        $validated = $validator->validated();
        $tokens = $validated['tokens'];
        $range = $tokens['reasoning'][$input['quality']];
        $usage = [
            'input_text_tokens' => (int) ceil(mb_strlen($input['prompt'], 'UTF-8') / $tokens['characters_per_text_token']),
            'input_image_tokens' => count($input['reference_images']) * (int) $tokens['per_reference_image'],
            'output_tokens' => (int) $tokens['output'][$input['quality']][$input['size']],
            'reasoning_tokens' => (int) $range['min'] + intdiv((int) $range['max'] - (int) $range['min'], 2),
        ];
        $maximumText = bcmul((string) $usage['input_text_tokens'], bcadd('1', $variance, 9), 9);
        $maximumTextInteger = bcadd($maximumText, '0', 0);
        $maximumUsage = [
            ...$usage,
            'input_text_tokens' => (int) $maximumTextInteger + (bccomp($maximumText, $maximumTextInteger, 9) > 0 ? 1 : 0),
            'reasoning_tokens' => (int) $range['max'],
        ];

        return [
            ...$this->calculate($usage, $validated['rates']),
            'maximum_user_cost' => $this->calculate($maximumUsage, $validated['rates'])['user_charge'],
            'usage' => $usage,
            'maximum_usage' => $maximumUsage,
            'rates' => $validated['rates'],
            'version' => 'v2',
        ];
    }
}
