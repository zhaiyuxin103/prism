<?php

use App\Actions\ApplyGenerationSnapshot;
use App\Actions\CreditLedger;
use App\Enums\GenerationStatus;
use App\Enums\SubmissionState;
use App\Jobs\SyncGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require_once __DIR__.'/../Fixtures/credits.php';

beforeEach(function () {
    config(['services.pixelforge.api_key' => 'credits-test-key', 'services.pixelforge.base_url' => 'https://pixelforge.promptedit.dev/v1']);
    Http::preventStrayRequests();
});

test('deducts the exact actual charge and releases the reservation once despite repeated and old snapshots', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->withCredits()->create();
    $generation = reserveCreditGeneration($user);
    $apply = app(ApplyGenerationSnapshot::class);
    $snapshot = creditGenerationSnapshot($generation);

    $apply->handle($generation->id, $snapshot);
    $apply->handle($generation->id, $snapshot);
    $apply->handle($generation->id, creditGenerationSnapshot($generation, 'processing'));

    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '9.937175000000000000', 'reserved' => '0.000000000000000000']);
    $this->assertDatabaseHas('credit_entries', ['generation_id' => $generation->id, 'type' => 'settlement', 'amount' => '-0.062825000000000000', 'reserved_amount' => '-0.086268750000000000']);
    $this->assertDatabaseHas('generation_settlements', ['generation_id' => $generation->id, 'user_charge' => '0.062825000000000000']);
    $this->assertDatabaseCount('credit_entries', 3);
    $this->assertDatabaseCount('generation_settlements', 1);
    Queue::assertPushed(SyncGeneration::class, 1);
    Http::assertSentCount(1);
});

test('provider failure releases reserved credits without charging the balance', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->withCredits()->create();
    $generation = reserveCreditGeneration($user);
    $apply = app(ApplyGenerationSnapshot::class);

    $apply->handle($generation->id, creditGenerationSnapshot($generation, 'failed'));
    $apply->handle($generation->id, creditGenerationSnapshot($generation, 'failed'));

    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '10.000000000000000000', 'reserved' => '0.000000000000000000']);
    $this->assertDatabaseHas('credit_entries', ['generation_id' => $generation->id, 'type' => 'settlement', 'amount' => '0.000000000000000000']);
    $this->assertDatabaseCount('credit_entries', 3);
    Queue::assertPushed(SyncGeneration::class, 1);
});

test('definitive rejection before acceptance releases the reservation', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake([
        'https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing()),
        'https://pixelforge.promptedit.dev/v1/generations' => Http::response(['error' => ['code' => 'content_policy_violation']], 400),
    ]);
    $user = User::factory()->withCredits()->create();
    $generation = reserveCreditGeneration($user);

    app()->call([new SyncGeneration($generation->id), 'handle']);

    expect($generation->refresh()->status)->toBe(GenerationStatus::Failed);
    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '10.000000000000000000', 'reserved' => '0.000000000000000000']);
    $this->assertDatabaseCount('credit_entries', 3);
    Http::assertSentCount(2);
    Queue::assertPushed(SyncGeneration::class, 1);
});

test('lost provider responses keep credits reserved while paused and settle a late success once', function () {
    $this->freezeTime();
    config(['services.pixelforge.submit_attempts' => 1]);
    Queue::fake([SyncGeneration::class]);
    Http::fake([
        'https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing()),
        'https://pixelforge.promptedit.dev/v1/generations' => Http::failedConnection(),
    ]);
    $user = User::factory()->withCredits()->create();
    $generation = reserveCreditGeneration($user);

    app()->call([new SyncGeneration($generation->id), 'handle']);

    expect($generation->refresh()->submission_state)->toBe(SubmissionState::Uncertain);
    expect($generation->next_attempt_at)->toBeNull();
    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '10.000000000000000000', 'reserved' => '0.086268750000000000']);
    $this->postJson(route('generations.resume', $generation))->assertAccepted();
    $this->assertDatabaseCount('credit_entries', 2);
    app(ApplyGenerationSnapshot::class)->handle($generation->id, creditGenerationSnapshot($generation));
    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '9.937175000000000000', 'reserved' => '0.000000000000000000']);
    $this->assertDatabaseCount('credit_entries', 3);
    Http::assertSentCount(2);
    Queue::assertPushed(SyncGeneration::class);
});

test('settling one generation preserves other reservations and later purchases', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->withCredits()->create();
    $first = reserveCreditGeneration($user);
    $second = reserveCreditGeneration($user);
    app(CreditLedger::class)->purchase($user, '25', (string) Str::uuid());

    app(ApplyGenerationSnapshot::class)->handle($first->id, creditGenerationSnapshot($first));

    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '34.937175000000000000', 'reserved' => '0.086268750000000000']);
    expect(app(CreditLedger::class)->balance($user)['available'])->toBe('34.850906250000000000');
    expect($second->refresh()->status)->toBe(GenerationStatus::Queued);
    Queue::assertPushed(SyncGeneration::class, 2);
});

test('records real costs above the reservation and blocks further spending until topped up', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->create();
    fundCredits($user, '0.086268750000000000');
    $generation = reserveCreditGeneration($user);
    $snapshot = creditGenerationSnapshot($generation);
    $snapshot['rates']['output_tokens'] = '400.00';

    app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot);

    $this->assertDatabaseHas('generation_settlements', ['generation_id' => $generation->id, 'user_charge' => '0.557825000000000000']);
    expect(app(CreditLedger::class)->balance($user))->toBe(['balance' => '-0.471556250000000000', 'reserved' => '0.000000000000000000', 'available' => '-0.471556250000000000', 'has_debt' => true]);
    $request = creditGenerationRequest($user);
    $this->postJson(route('generations.store'), $request)->assertUnprocessable()->assertInvalid('balance');
    app(CreditLedger::class)->purchase($user, '10', (string) Str::uuid());
    $this->postJson(route('generations.store'), $request)->assertAccepted();
    expect(app(CreditLedger::class)->balance($user)['available'])->toBe('9.442175000000000000');
    Queue::assertPushed(SyncGeneration::class, 2);
});

test('retains all eighteen fractional digits when settling', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->withCredits()->create();
    $generation = reserveCreditGeneration($user);
    $snapshot = creditGenerationSnapshot($generation);
    $snapshot['rates'] = ['input_text_tokens' => '0.000000001', 'input_image_tokens' => '0', 'output_tokens' => '0', 'reasoning_tokens' => '0'];
    $snapshot['usage']['input_text_tokens'] = 1;

    app(ApplyGenerationSnapshot::class)->handle($generation->id, $snapshot);

    $this->assertDatabaseHas('credit_entries', ['generation_id' => $generation->id, 'type' => 'settlement', 'amount' => '-0.000000000000001250']);
    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '9.999999999999998750']);
    Queue::assertPushed(SyncGeneration::class, 1);
});

test('a deleted user account still receives the final settlement without losing its ledger', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->withCredits()->create();
    $generation = reserveCreditGeneration($user);
    $accountId = $generation->credit_account_id;
    $user->delete();

    app(ApplyGenerationSnapshot::class)->handle($generation->id, creditGenerationSnapshot($generation));

    $this->assertDatabaseHas('credit_accounts', ['id' => $accountId, 'user_id' => null, 'balance' => '9.937175000000000000', 'reserved' => '0.000000000000000000']);
    $this->assertDatabaseHas('generations', ['id' => $generation->id, 'user_id' => null, 'status' => 'succeeded']);
    $this->assertDatabaseCount('credit_entries', 3);
    Queue::assertPushed(SyncGeneration::class, 1);
});

test('settlement and ledger updates roll back together when saving the terminal generation fails', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->withCredits()->create();
    $generation = reserveCreditGeneration($user);
    Generation::saving(function (Generation $model): void {
        throw_if($model->status->isTerminal(), RuntimeException::class, 'Simulated persistence failure.');
    });

    expect(fn () => app(ApplyGenerationSnapshot::class)->handle($generation->id, creditGenerationSnapshot($generation)))->toThrow(RuntimeException::class, 'Simulated persistence failure.');

    $this->assertDatabaseCount('generation_settlements', 0);
    $this->assertDatabaseCount('credit_entries', 2);
    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '10.000000000000000000', 'reserved' => '0.086268750000000000']);
    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued);
    Queue::assertPushed(SyncGeneration::class, 1);
});

test('generations submitted before balances were enabled do not retroactively deduct credits', function () {
    $user = User::factory()->withCredits()->create();
    $generation = Generation::factory()->for($user)->create();

    app(ApplyGenerationSnapshot::class)->handle($generation->id, creditGenerationSnapshot($generation));

    $this->assertDatabaseCount('generation_settlements', 1);
    $this->assertDatabaseCount('credit_entries', 1);
    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '10.000000000000000000']);
    Http::assertNothingSent();
});
