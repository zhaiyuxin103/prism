<?php

use App\Actions\CreditLedger;
use App\Jobs\SyncGeneration;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../Fixtures/credits.php';

beforeEach(function () {
    config(['services.pixelforge.api_key' => 'credits-test-key', 'services.pixelforge.base_url' => 'https://pixelforge.promptedit.dev/v1', 'inertia.ssr.enabled' => false]);
    Http::preventStrayRequests();
});

test('guests cannot purchase credits or read history', function () {
    $this->postJson(route('credits.store'))->assertUnauthorized();
    $this->get(route('credits.index'))->assertRedirect(route('login'));
    $this->assertDatabaseCount('credit_entries', 0);
});

test('unverified users cannot purchase credits', function () {
    $this->actingAs(User::factory()->unverified()->create())->postJson(route('credits.store'), ['amount' => '10', 'client_request_id' => (string) Str::uuid()])->assertForbidden();
    $this->assertDatabaseCount('credit_accounts', 0);
});

test('new users start with zero credits and no financial activity', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('credits.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('Credits')
        ->where('credits', ['balance' => '0.000000000000000000', 'reserved' => '0.000000000000000000', 'available' => '0.000000000000000000', 'has_debt' => false])
        ->has('entries.data', 0));
    $this->assertDatabaseCount('credit_accounts', 0);
});

test('purchases a fixed package only for the authenticated user and replays it once', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $request = ['amount' => '25', 'client_request_id' => (string) Str::uuid(), 'user_id' => $other->id, 'balance' => '999', 'reserved' => '-10'];

    $response = $this->actingAs($user)->postJson(route('credits.store'), $request)->assertCreated()->assertJsonPath('credits.balance', '25.000000000000000000');
    $this->postJson(route('credits.store'), $request)->assertOk()->assertJsonPath('entry.id', $response->json('entry.id'))->assertJsonPath('credits.balance', '25.000000000000000000');

    $this->assertDatabaseCount('credit_entries', 1);
    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '25.000000000000000000', 'reserved' => '0.000000000000000000']);
    $this->assertDatabaseMissing('credit_accounts', ['user_id' => $other->id]);
    Http::assertNothingSent();
});

test('rejects purchase ID reuse with another package', function () {
    $user = User::factory()->create();
    $id = (string) Str::uuid();
    $this->actingAs($user)->postJson(route('credits.store'), ['amount' => '10', 'client_request_id' => $id])->assertCreated();

    $this->postJson(route('credits.store'), ['amount' => '50', 'client_request_id' => $id])->assertConflict();

    $this->assertDatabaseCount('credit_entries', 1);
    $this->assertDatabaseHas('credit_accounts', ['user_id' => $user->id, 'balance' => '10.000000000000000000']);
});

test('scopes purchase identifiers and financial history to the account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $id = (string) Str::uuid();
    app(CreditLedger::class)->purchase($other, '50', $id);
    app(CreditLedger::class)->purchase($user, '10', $id);

    $this->actingAs($user)->get(route('credits.index', ['user_id' => $other->id]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('credits.balance', '10.000000000000000000')->has('entries.data', 1)->where('entries.data.0.amount', '10.000000000000000000'));
});

test('rejects invalid purchase input without changing the ledger', function (array $input, string $field, string $message) {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('credits.store'), [...['amount' => '10', 'client_request_id' => (string) Str::uuid()], ...$input])
        ->assertUnprocessable()->assertInvalid([$field => $message]);

    $this->assertDatabaseCount('credit_accounts', 0);
    $this->assertDatabaseCount('credit_entries', 0);
})->with([
    'negative' => [['amount' => '-10'], 'amount', 'Choose a credit package'],
    'zero' => [['amount' => '0'], 'amount', 'Choose a credit package'],
    'fraction' => [['amount' => '0.0001'], 'amount', 'Choose a credit package'],
    'too large' => [['amount' => '9999999999999999999'], 'amount', 'Choose a credit package'],
    'exponent' => [['amount' => '1e1'], 'amount', 'Choose a credit package'],
    'numeric type' => [['amount' => 10], 'amount', 'must be a string'],
    'missing amount' => [['amount' => null], 'amount', 'required'],
    'invalid ID' => [['client_request_id' => 'invalid'], 'client_request_id', 'valid UUID'],
    'missing ID' => [['client_request_id' => null], 'client_request_id', 'required'],
]);

test('blocks generation without funds and dispatches no provider job', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->create();
    $request = creditGenerationRequest($user);

    $this->postJson(route('generations.store'), $request)->assertUnprocessable()->assertInvalid(['balance' => 'Not enough available credits.']);

    $this->assertDatabaseCount('generations', 0);
    $this->assertDatabaseCount('credit_entries', 0);
    $this->assertDatabaseCount('credit_accounts', 0);
    Queue::assertNothingPushed();
    Http::assertSentCount(1);
});

test('requires the upper estimate even when the midpoint is affordable', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->create();
    fundCredits($user, '0.080000000000000000');

    $this->postJson(route('generations.store'), creditGenerationRequest($user))->assertUnprocessable()->assertInvalid('balance');

    $this->assertDatabaseCount('generations', 0);
    $this->assertDatabaseCount('credit_entries', 1);
    Queue::assertNothingPushed();
});

test('reserves the exact upper estimate and allows an idempotent replay without more funds', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->create();
    $account = fundCredits($user, '0.086268750000000000');
    $request = creditGenerationRequest($user);

    $response = $this->postJson(route('generations.store'), $request)->assertAccepted()->assertJsonPath('data.reserved_cost', '0.086268750000000000');
    $this->postJson(route('generations.store'), $request)->assertAccepted()->assertJsonPath('data.id', $response->json('data.id'));

    expect($account->refresh()->available())->toBe('0.000000000000000000');
    $this->assertDatabaseHas('credit_accounts', ['id' => $account->id, 'balance' => '0.086268750000000000', 'reserved' => '0.086268750000000000']);
    $this->assertDatabaseCount('credit_entries', 2);
    Queue::assertPushed(SyncGeneration::class);
});

test('competing submissions cannot spend credits already reserved and can retry after a purchase', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->create();
    $account = fundCredits($user, '0.100000000000000000');
    $request = creditGenerationRequest($user);
    $this->postJson(route('generations.store'), $request)->assertAccepted();
    $second = [...$request, 'client_request_id' => (string) Str::uuid()];

    $this->postJson(route('generations.store'), $second)->assertUnprocessable()->assertInvalid('balance');
    $this->postJson(route('credits.store'), ['amount' => '10', 'client_request_id' => (string) Str::uuid()])->assertCreated();
    $this->postJson(route('generations.store'), $second)->assertAccepted();

    $this->assertDatabaseCount('generations', 2);
    $this->assertDatabaseCount('credit_entries', 4);
    expect($account->refresh()->available())->toBe('9.927462500000000000');
    Queue::assertPushed(SyncGeneration::class, 2);
});

test('changed generation parameters cannot reuse a reservation', function () {
    Queue::fake([SyncGeneration::class]);
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $user = User::factory()->withCredits()->create();
    $request = creditGenerationRequest($user);
    $this->postJson(route('generations.store'), $request)->assertAccepted();

    $this->postJson(route('generations.store'), [...$request, 'prompt' => 'A different lake'])->assertConflict();

    $this->assertDatabaseCount('generations', 1);
    $this->assertDatabaseCount('credit_entries', 2);
    Queue::assertPushed(SyncGeneration::class, 1);
});

test('balance history is paginated newest first', function () {
    $user = User::factory()->create();
    foreach (range(1, 21) as $index) {
        app(CreditLedger::class)->purchase($user, '10', (string) Str::uuid());
    }

    $this->actingAs($user)->get(route('credits.index', ['page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('credits.balance', '210.000000000000000000')->has('entries.data', 1)->where('entries.meta.total', 21)
        ->where('entries.data.0.balance_after', '10.000000000000000000'));
});

test('dashboard refreshes credit information with generation status', function () {
    $user = User::factory()->withCredits()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('credits.available', '10.000000000000000000')->where('creditPackages', ['10', '25', '50']));
});
