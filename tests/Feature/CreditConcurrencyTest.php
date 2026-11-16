<?php

use App\Actions\ApplyGenerationSnapshot;
use App\Actions\CreateGeneration;
use App\Actions\CreditLedger;
use App\Actions\GenerationPricing;
use App\Models\CreditAccount;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Fixtures/credits.php';

test('concurrent purchases reservations and settlements preserve one exact account balance', function () {
    $connection = config('database.connections.'.config('database.default'));
    if ($connection['driver'] !== 'pgsql' || ! str_starts_with($connection['database'], 'prism_credits_test_')) {
        $this->markTestSkipped('Requires a dedicated PostgreSQL database named prism_credits_test_*.');
    }

    config(['services.pixelforge.api_key' => 'credits-test-key', 'services.pixelforge.base_url' => 'https://provider.test/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://provider.test/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->create();
    $userId = $user->id;
    $request = creditGenerationRequest($user);
    $input = array_intersect_key($request, array_flip(['prompt', 'size', 'quality', 'reference_images']));
    $pricing = pixelforgePricing();
    $purchaseId = (string) Str::uuid();
    $setup = Closure::bind(static function () use ($connection): void {
        config(['database.connections.credit_concurrency' => $connection]);
        DB::setDefaultConnection('credit_concurrency');
        Http::preventStrayRequests();
    }, null, null);

    /** Commit fixtures so independent PHP processes can see them in the dedicated test database. */
    DB::commit();
    $purchase = Closure::bind(static function () use ($setup, $userId, $purchaseId): int {
        $setup();

        return app(CreditLedger::class)->purchase(User::query()->findOrFail($userId), '10', $purchaseId)->id;
    }, null, null);
    $purchaseIds = Concurrency::driver('process')->run([$purchase, $purchase, $purchase, $purchase], timeout: 20);
    expect(array_unique($purchaseIds))->toHaveCount(1);
    $account = CreditAccount::query()->where('user_id', $userId)->sole();
    expect($account->balance)->toBe('10.000000000000000000');
    expect($account->entries()->count())->toBe(1);

    /** Each generation reserves $6.90626875; only one fits the $10 balance. */
    $pricing['rates']['output_tokens'] = '5000';
    $estimate = app(GenerationPricing::class)->estimate($input, $pricing);
    $quote = Crypt::encryptString(json_encode([
        'user_id' => $userId, 'request_hash' => hash('sha256', json_encode($input, JSON_THROW_ON_ERROR)),
        'expires_at' => now()->addMinutes(5)->timestamp, 'estimate' => $estimate,
    ], JSON_THROW_ON_ERROR));
    $submit = Closure::bind(static function () use ($setup, $userId, $input, $quote, $pricing): bool {
        $setup();
        config(['services.pixelforge.api_key' => 'credits-test-key', 'services.pixelforge.base_url' => 'https://provider.test/v1', 'cache.default' => 'array']);
        Http::fake(['https://provider.test/v1/pricing' => Http::response($pricing)]);
        try {
            app(CreateGeneration::class)->handle(User::query()->findOrFail($userId), $input, (string) Str::uuid(), $quote);

            return true;
        } catch (ValidationException $exception) {
            if (isset($exception->errors()['balance'])) {
                return false;
            }
            throw $exception;
        }
    }, null, null);
    $accepted = Concurrency::driver('process')->run([$submit, $submit, $submit, $submit], timeout: 20);
    expect(array_filter($accepted))->toHaveCount(1);
    expect(Generation::query()->where('user_id', $userId)->count())->toBe(1);
    $generation = Generation::query()->where('user_id', $userId)->sole();
    $generationId = $generation->id;
    $snapshot = creditGenerationSnapshot($generation);
    $settle = Closure::bind(static function () use ($setup, $generationId, $snapshot): void {
        $setup();
        app(ApplyGenerationSnapshot::class)->handle($generationId, $snapshot);
    }, null, null);
    $topupId = (string) Str::uuid();
    $topup = Closure::bind(static function () use ($setup, $userId, $topupId): void {
        $setup();
        app(CreditLedger::class)->purchase(User::query()->findOrFail($userId), '25', $topupId);
    }, null, null);
    Concurrency::driver('process')->run([$settle, $topup, $settle, $topup], timeout: 20);

    expect($account->refresh()->balance)->toBe('34.937175000000000000');
    expect($account->reserved)->toBe('0.000000000000000000');
    expect($account->entries()->count())->toBe(4);
    expect($generation->settlement()->count())->toBe(1);
    Http::assertSentCount(1);
})->group('credit-concurrency');
