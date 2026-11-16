<?php

use App\Models\CreditAccount;
use App\Models\CreditEntry;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

require_once __DIR__.'/pixelforge.php';

function fundCredits(User $user, string $balance = '10.000000000000000000'): CreditAccount
{
    $account = CreditAccount::factory()->for($user)->create(['balance' => $balance]);
    CreditEntry::factory()->for($account, 'creditAccount')->create(['amount' => $balance, 'balance_after' => $balance]);

    return $account;
}

/** @return array<string, mixed> */
function creditGenerationRequest(User $user): array
{
    $input = ['prompt' => 'A lake', 'size' => '1024x1024', 'quality' => 'medium', 'reference_images' => []];
    actingAs($user);
    $quote = postJson(route('generations.estimate'), $input)->assertOk()->json('quote');

    return [...$input, 'quote' => $quote, 'client_request_id' => (string) Str::uuid()];
}

/** @return array<string, mixed> */
function creditGenerationSnapshot(Generation $generation, string $status = 'succeeded'): array
{
    $snapshot = pixelforgeSnapshot($generation, $status);
    if ($status === 'succeeded') {
        $snapshot['usage'] = ['input_text_tokens' => 2, 'input_image_tokens' => 0, 'output_tokens' => 1100, 'reasoning_tokens' => 500, 'total_tokens' => 1602];
    }

    return $snapshot;
}

function reserveCreditGeneration(User $user): Generation
{
    $response = postJson(route('generations.store'), creditGenerationRequest($user))->assertAccepted();

    return Generation::query()->findOrFail($response->json('data.id'));
}
