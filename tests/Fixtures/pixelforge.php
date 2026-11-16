<?php

use App\Models\Generation;

/** @return array<string, mixed> */
function pixelforgePricing(): array
{
    return [
        'currency' => 'usd',
        'unit' => 'per_1m_tokens',
        'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => ['low' => ['min' => 0, 'max' => 500], 'medium' => ['min' => 200, 'max' => 2000], 'high' => ['min' => 1000, 'max' => 8000]],
        ],
    ];
}

/** @return array<string, mixed> */
function pixelforgeSnapshot(Generation $generation, string $status = 'succeeded'): array
{
    return [
        ...$generation->request_payload,
        'id' => $generation->provider_generation_id ?? 'gen_test',
        'status' => $status,
        'rates' => pixelforgePricing()['rates'],
        'usage' => $status === 'succeeded' ? ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 3184, 'total_tokens' => 10411] : null,
        'image_url' => $status === 'succeeded' ? 'https://pixelforge.promptedit.dev/images/example.png' : null,
        'image_url_expires_at' => $status === 'succeeded' ? now()->addDay()->toIso8601String() : null,
        'created_at' => now()->subMinute()->toIso8601String(),
        'started_at' => $status === 'queued' ? null : now()->subSeconds(30)->toIso8601String(),
        'completed_at' => in_array($status, ['succeeded', 'failed']) ? now()->toIso8601String() : null,
        'error' => $status === 'failed' ? ['code' => 'generation_failed', 'message' => 'Could not generate this image.'] : null,
    ];
}
