<?php

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LoginLink\Exceptions\DidNotFindUserToLogIn;
use Spatie\LoginLink\Exceptions\NotAllowedInCurrentEnvironment;
use Spatie\LoginLink\Exceptions\NotAllowedInCurrentHost;

beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
});

test('login lists seeded users with links to their own team dashboards', function () {
    $this->seed(DemoUserSeeder::class);
    $user = User::query()->where('email', 'user@demo.prism.test')->firstOrFail();

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('loginLinks', 4)
        ->where('loginLinks.0.email', 'admin@demo.prism.test')
        ->where('loginLinks.1.name', 'Demo User')
        ->where('loginLinks.1.redirect_url', route('dashboard', ['current_team' => $user->currentTeam->slug])));
});

test('package login authenticates each seeded role and redirects to its team', function (string $email, bool $isAdmin) {
    $this->seed(DemoUserSeeder::class);
    $user = User::query()->where('email', $email)->firstOrFail();
    $destination = route('dashboard', ['current_team' => $user->currentTeam->slug]);

    $this->post(route('loginLinkLogin'), ['email' => $email, 'redirect_url' => $destination])
        ->assertRedirect($destination);

    $this->assertAuthenticatedAs($user);
    expect($user->can('view-admin-report'))->toBe($isAdmin);
    $this->get($destination)->assertOk();
})->with([
    'administrator' => ['admin@demo.prism.test', true],
    'regular user' => ['user@demo.prism.test', false],
    'low balance' => ['low-balance@demo.prism.test', false],
    'new user' => ['new-user@demo.prism.test', false],
]);

test('demo environment enables the seeded login links', function () {
    app()->detectEnvironment(fn (): string => 'demo');
    $this->seed(DemoUserSeeder::class);

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('loginLinks', 4));
});

test('production hides login links and rejects direct package login', function () {
    $this->seed(DemoUserSeeder::class);
    app()->detectEnvironment(fn (): string => 'production');
    Exceptions::fake();

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('loginLinks', []));
    $this->withSession(['_token' => 'login-link-test-token'])
        ->post(route('loginLinkLogin'), ['email' => 'admin@demo.prism.test', '_token' => 'login-link-test-token'])
        ->assertServerError();

    $this->assertGuest();
    Exceptions::assertReported(NotAllowedInCurrentEnvironment::class);
});

test('another host cannot expose or use login links', function () {
    $this->seed(DemoUserSeeder::class);
    config(['login-link.allowed_hosts' => ['unexpected.test']]);
    Exceptions::fake();

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('loginLinks', []));
    $this->post(route('loginLinkLogin'), ['email' => 'admin@demo.prism.test'])->assertServerError();

    $this->assertGuest();
    Exceptions::assertReported(NotAllowedInCurrentHost::class);
});

test('login links stay empty until demo users are seeded', function () {
    User::factory()->create();

    $this->get(route('login'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('loginLinks', []));
});

test('package login does not automatically create missing users', function () {
    Exceptions::fake();

    $this->post(route('loginLinkLogin'), ['email' => 'user@demo.prism.test'])->assertServerError();

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    Exceptions::assertReported(DidNotFindUserToLogIn::class);
});

test('authenticated users cannot switch accounts through guest login links', function () {
    $this->seed(DemoUserSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('loginLinkLogin'), ['email' => 'admin@demo.prism.test'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('package login attempts are rate limited', function () {
    Exceptions::fake();
    foreach (range(1, 10) as $attempt) {
        $this->postJson(route('loginLinkLogin'), ['email' => 'missing@demo.prism.test'])->assertServerError();
    }

    $this->postJson(route('loginLinkLogin'), ['email' => 'admin@demo.prism.test'])->assertTooManyRequests();
    $this->assertGuest();
    Exceptions::assertReported(DidNotFindUserToLogIn::class);
});
