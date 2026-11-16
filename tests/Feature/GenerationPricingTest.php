<?php

use App\Actions\GenerationPricing;

require_once __DIR__.'/../Fixtures/pixelforge.php';

test('all four token types and markup are calculated without rounding', function () {
    $result = app(GenerationPricing::class)->calculate(
        ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 3184],
        pixelforgePricing()['rates'],
    );

    expect($result)->toBe(['provider_cost' => '0.305510000000000000', 'user_charge' => '0.381887500000000000']);
});

test('unicode prompts and reasoning midpoint are used for estimates', function () {
    $estimate = app(GenerationPricing::class)->estimate([
        'prompt' => '你好世界🌈', 'size' => '1024x1024', 'quality' => 'medium', 'reference_images' => ['https://example.com/a.png'],
    ], pixelforgePricing());

    expect($estimate['usage'])->toBe(['input_text_tokens' => 2, 'input_image_tokens' => 765, 'output_tokens' => 1100, 'reasoning_tokens' => 1100]);
    expect($estimate['user_charge'])->toBe('0.081762500000000000');
});

test('a fraction of a nanodollar survives the markup', function () {
    $result = app(GenerationPricing::class)->calculate(
        ['input_text_tokens' => 1, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0],
        ['input_text_tokens' => '0.001', 'input_image_tokens' => '0', 'output_tokens' => '0', 'reasoning_tokens' => '0'],
    );

    expect($result)->toBe(['provider_cost' => '0.000000001000000000', 'user_charge' => '0.000000001250000000']);
});

test('missing or invalid usage never silently becomes zero cost', function (array $usage) {
    expect(fn () => app(GenerationPricing::class)->calculate($usage, pixelforgePricing()['rates']))->toThrow(InvalidArgumentException::class);
})->with([
    'missing reasoning' => [['input_text_tokens' => 1, 'input_image_tokens' => 0, 'output_tokens' => 1]],
    'negative tokens' => [['input_text_tokens' => -1, 'input_image_tokens' => 0, 'output_tokens' => 1, 'reasoning_tokens' => 0]],
    'fractional tokens' => [['input_text_tokens' => 1.5, 'input_image_tokens' => 0, 'output_tokens' => 1, 'reasoning_tokens' => 0]],
]);

test('five and nine decimal rates retain every fraction after markup', function (string $rate, string $provider, string $charge) {
    $result = app(GenerationPricing::class)->calculate(
        ['input_text_tokens' => 1, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0],
        ['input_text_tokens' => $rate, 'input_image_tokens' => '0', 'output_tokens' => '0', 'reasoning_tokens' => '0'],
    );

    expect($result)->toBe(['provider_cost' => $provider, 'user_charge' => $charge]);
})->with([
    ['0.00001', '0.000000000010000000', '0.000000000012500000'],
    ['0.000000001', '0.000000000000001000', '0.000000000000001250'],
]);

test('large amounts do not overflow a scaled integer', function () {
    $result = app(GenerationPricing::class)->calculate(
        ['input_text_tokens' => 1000000, 'input_image_tokens' => 0, 'output_tokens' => 0, 'reasoning_tokens' => 0],
        ['input_text_tokens' => '999999999.999999999', 'input_image_tokens' => '0', 'output_tokens' => '0', 'reasoning_tokens' => '0'],
    );

    expect($result)->toBe(['provider_cost' => '999999999.999999999000000000', 'user_charge' => '1249999999.999999998750000000']);
});

test('the upper estimate covers the provider worked example and includes markup', function () {
    $estimate = app(GenerationPricing::class)->estimate([
        'prompt' => str_repeat('a', 400), 'size' => '1536x1024', 'quality' => 'high',
        'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
    ], pixelforgePricing());

    expect($estimate['maximum_usage'])->toBe(['input_text_tokens' => 115, 'input_image_tokens' => 1530, 'output_tokens' => 6450, 'reasoning_tokens' => 8000]);
    expect($estimate['user_charge'])->toBe('0.412562500000000000');
    expect($estimate['maximum_user_cost'])->toBe('0.467343750000000000');
});

test('text variance is rounded up for short unicode prompts', function () {
    $estimate = app(GenerationPricing::class)->estimate([
        'prompt' => '你好世界🌈', 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => [],
    ], pixelforgePricing());

    expect($estimate['maximum_usage']['input_text_tokens'])->toBe(3);
    expect($estimate['maximum_user_cost'])->toBe('0.021831250000000000');
});
