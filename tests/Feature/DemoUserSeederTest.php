<?php

use App\Actions\CreditLedger;
use App\Jobs\SyncGeneration;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

require_once __DIR__.'/../Fixtures/credits.php';

beforeEach(function () {
    config(['demo.enabled' => true]);
});

test('default seeding creates verified demo users with personal teams and balanced opening entries', function () {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 4);
    $this->assertDatabaseCount('teams', 4);
    $this->assertDatabaseCount('team_members', 4);
    $this->assertDatabaseCount('credit_accounts', 4);
    $this->assertDatabaseCount('credit_entries', 3);
    $this->assertDatabaseCount('generations', 0);

    foreach ([
        'admin@demo.prism.test' => ['25.000000000000000000', true],
        'user@demo.prism.test' => ['10.000000000000000000', false],
        'low-balance@demo.prism.test' => ['0.001000000000000000', false],
        'new-user@demo.prism.test' => ['0.000000000000000000', false],
    ] as $email => [$balance, $isAdmin]) {
        $user = User::query()->where('email', $email)->firstOrFail();
        expect($user->is_demo)->toBeTrue();
        expect($user->is_admin)->toBe($isAdmin);
        expect($user->hasVerifiedEmail())->toBeTrue();
        expect(Hash::check('password', $user->password))->toBeFalse();
        expect($user->ownsTeam($user->currentTeam))->toBeTrue();
        expect($user->currentTeam->is_personal)->toBeTrue();
        expect($user->creditAccount->available())->toBe($balance);
        $entryTotal = $user->creditAccount->entries->reduce(fn (string $total, $entry): string => bcadd($total, $entry->amount, 18), '0.000000000000000000');
        expect($entryTotal)->toBe($balance);
    }
});

test('repeated seeding preserves balances passwords and existing activity', function () {
    $this->seed(DatabaseSeeder::class);
    $user = User::query()->where('email', 'low-balance@demo.prism.test')->firstOrFail();
    $password = $user->password;
    app(CreditLedger::class)->purchase($user, '10', (string) Str::uuid());

    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 4);
    $this->assertDatabaseCount('teams', 4);
    $this->assertDatabaseCount('team_members', 4);
    $this->assertDatabaseCount('credit_entries', 4);
    expect($user->refresh()->password)->toBe($password);
    expect($user->creditAccount->balance)->toBe('10.001000000000000000');
});

test('disabled demo mode seeds no accounts', function () {
    config(['demo.enabled' => false]);

    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('credit_entries', 0);
});

test('a colliding real account is not promoted or modified and seeding rolls back', function () {
    $user = User::factory()->unverified()->create(['email' => 'user@demo.prism.test']);
    $password = $user->password;

    expect(fn () => $this->seed(DemoUserSeeder::class))->toThrow(RuntimeException::class, 'A demo email is already used by a non-demo account.');

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('teams', 1);
    $this->assertDatabaseCount('credit_entries', 0);
    expect($user->refresh()->is_demo)->toBeFalse();
    expect($user->is_admin)->toBeFalse();
    expect($user->hasVerifiedEmail())->toBeFalse();
    expect($user->password)->toBe($password);
});

test('seeded low and zero balances block generation without reserving funds or dispatching jobs', function (string $email) {
    config(['services.pixelforge.api_key' => 'demo-test-key', 'services.pixelforge.base_url' => 'https://pixelforge.promptedit.dev/v1']);
    Queue::fake([SyncGeneration::class]);
    Http::preventStrayRequests();
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $this->seed(DemoUserSeeder::class);
    $user = User::query()->where('email', $email)->firstOrFail();
    $balance = $user->creditAccount->balance;

    $this->postJson(route('generations.store'), creditGenerationRequest($user))
        ->assertUnprocessable()->assertInvalid(['balance' => 'Not enough available credits.']);

    $this->assertDatabaseCount('generations', 0);
    $this->assertDatabaseCount('credit_entries', 3);
    expect($user->creditAccount->refresh()->balance)->toBe($balance);
    expect($user->creditAccount->reserved)->toBe('0.000000000000000000');
    Queue::assertNothingPushed();
    Http::assertSentCount(1);
})->with(['low-balance@demo.prism.test', 'new-user@demo.prism.test']);

test('the seeded regular user can generate but cannot open admin reports', function () {
    config(['services.pixelforge.api_key' => 'demo-test-key', 'services.pixelforge.base_url' => 'https://pixelforge.promptedit.dev/v1']);
    Queue::fake([SyncGeneration::class]);
    Http::preventStrayRequests();
    Http::fake(['https://pixelforge.promptedit.dev/v1/pricing' => Http::response(pixelforgePricing())]);
    $this->seed(DemoUserSeeder::class);
    $user = User::query()->where('email', 'user@demo.prism.test')->firstOrFail();

    $this->postJson(route('generations.store'), creditGenerationRequest($user))->assertAccepted();

    $this->assertDatabaseCount('generations', 1);
    $this->assertDatabaseCount('credit_entries', 4);
    Queue::assertPushed(SyncGeneration::class, 1);
    Http::assertSentCount(1);
    $this->get(route('admin.report'))->assertForbidden();
});
