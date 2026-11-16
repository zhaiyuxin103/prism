<?php

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['demo.enabled' => true, 'inertia.ssr.enabled' => false]);
});

test('login offers seeded demo accounts with their current available balances', function () {
    $this->seed(DemoUserSeeder::class);
    $user = User::query()->where('email', 'user@demo.prism.test')->firstOrFail();
    $user->creditAccount->update(['reserved' => '2.000000000000000000']);

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('demoAccounts', 4)
        ->where('demoAccounts.0.account', 'admin')
        ->where('demoAccounts.1.available', '8.000000000000000000')
        ->where('demoAccounts.2.available', '0.001000000000000000')
        ->where('demoAccounts.3.available', '0.000000000000000000'));
});

test('logs into the selected demo account and its team with a fresh session', function (string $account, string $email, bool $isAdmin) {
    $this->seed(DemoUserSeeder::class);
    $user = User::query()->where('email', $email)->firstOrFail();
    $this->withSession(['login.id' => 999, 'auth.password_confirmed_at' => time(), 'url.intended' => 'https://unexpected.test']);
    $previousSession = session()->getId();
    $previousToken = session()->token();

    $this->post(route('loginLinkLogin'), ['account' => $account])
        ->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]))
        ->assertSessionMissing('login.id')->assertSessionMissing('auth.password_confirmed_at')->assertSessionMissing('url.intended');

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($previousSession);
    expect(session()->token())->not->toBe($previousToken);
    expect($user->can('view-admin-report'))->toBe($isAdmin);
    $this->get(route('dashboard', ['current_team' => $user->currentTeam->slug]))->assertOk();
})->with([
    'administrator' => ['admin', 'admin@demo.prism.test', true],
    'regular user' => ['user', 'user@demo.prism.test', false],
    'low balance' => ['low-balance', 'low-balance@demo.prism.test', false],
    'new user' => ['new-user', 'new-user@demo.prism.test', false],
]);

test('ignores package identity guard model and redirect overrides in body and query', function (bool $inQuery) {
    $this->seed(DemoUserSeeder::class);
    $other = User::factory()->admin()->create();
    $user = User::query()->where('email', 'user@demo.prism.test')->firstOrFail();
    $overrides = [
        'key' => $other->id, 'email' => $other->email, 'guard' => ['invalid'],
        'user_model' => User::class, 'user_attributes' => json_encode(['id' => $other->id]),
        'redirect_url' => 'https://unexpected.test',
    ];

    $this->post(route('loginLinkLogin', $inQuery ? $overrides : []), [
        'account' => 'user', ...($inQuery ? [] : $overrides),
    ])->assertRedirect(route('dashboard', ['current_team' => $user->currentTeam->slug]));

    $this->assertAuthenticatedAs($user);
})->with(['body' => false, 'query' => true]);

test('does not expose or accept demo login when disabled or outside allowed hosts and environments', function (string $restriction) {
    $this->seed(DemoUserSeeder::class);
    config(match ($restriction) {
        'disabled' => ['demo.enabled' => false],
        'environment' => ['login-link.allowed_environments' => ['local']],
        'host' => ['login-link.allowed_hosts' => ['unexpected.test']],
    });

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('demoAccounts', []));
    $this->post(route('loginLinkLogin'), ['account' => 'admin'])->assertNotFound();

    $this->assertGuest();
})->with(['disabled', 'environment', 'host']);

test('rejects missing and unknown demo account selections', function (array $input, string $message) {
    $this->postJson(route('loginLinkLogin'), $input)->assertUnprocessable()->assertInvalid(['account' => $message]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
})->with([
    'missing' => [[], 'The account field is required.'],
    'unknown' => [['account' => 'someone-else'], 'The selected account is invalid.'],
]);

test('does not create missing demo users on login', function () {
    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('demoAccounts', []));
    $this->post(route('loginLinkLogin'), ['account' => 'user'])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

test('refuses a non-demo account even when its email matches a preset', function () {
    User::factory()->create(['email' => 'admin@demo.prism.test']);

    $this->post(route('loginLinkLogin'), ['account' => 'admin'])->assertNotFound();

    $this->assertGuest();
});

test('rate limits demo login attempts', function () {
    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('loginLinkLogin'), ['account' => 'unknown'])->assertUnprocessable();
    }

    $this->postJson(route('loginLinkLogin'), ['account' => 'admin'])->assertTooManyRequests();
    $this->assertGuest();
});
