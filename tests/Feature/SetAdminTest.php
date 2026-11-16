<?php

use App\Models\User;

test('grants administrator access by ID or case insensitive email', function (string $identifier) {
    $user = User::factory()->create(['email' => 'operator@example.com']);

    $this->artisan('users:admin', ['user' => $identifier === 'id' ? (string) $user->id : ' OPERATOR@EXAMPLE.COM ', '--no-interaction' => true])->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
})->with(['id', 'email']);

test('revokes administrator access', function () {
    $user = User::factory()->admin()->create();

    $this->artisan('users:admin', ['user' => $user->email, '--revoke' => true, '--no-interaction' => true])->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('reports an unchanged role without failing', function (bool $admin) {
    $user = User::factory()->create(['is_admin' => $admin]);

    $this->artisan('users:admin', ['user' => $user->id, '--revoke' => ! $admin])
        ->expectsOutput($admin ? 'This user is already an administrator.' : 'This user is not an administrator.')->assertSuccessful();

    expect($user->fresh()->is_admin)->toBe($admin);
})->with([true, false]);

test('requires an identifier for non interactive execution', function () {
    $user = User::factory()->create();

    $this->artisan('users:admin', ['--no-interaction' => true])
        ->expectsOutput('Pass a user ID or email when using --no-interaction.')->assertExitCode(2);

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('fails cleanly when no account matches', function (string $identifier) {
    $this->artisan('users:admin', ['user' => $identifier])->expectsOutput('No user found with that ID or email.')->assertFailed();
    $this->assertDatabaseCount('users', 0);
})->with(['999', 'missing@example.com', '9999999999999999999999999999999']);

test('does not launch a picker when no users exist', function () {
    $this->artisan('users:admin')->expectsOutput('No users exist. Ask the user to register first.')->assertFailed();
});

test('searches and selects a user without an argument', function (string $term) {
    $user = User::factory()->create(['name' => 'Report Operator', 'email' => 'operator@example.com']);
    $other = User::factory()->create();
    $search = $term === 'id' ? (string) $user->id : $term;

    $this->artisan('users:admin')
        ->expectsSearch('Select a user to grant administrator access', $user->id, $search, [
            'cancel' => 'Cancel without changes',
            $user->id => "#{$user->id} | {$user->name} | {$user->email} | user",
        ])->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeTrue();
    expect($other->fresh()->is_admin)->toBeFalse();
})->with(['Operator', 'OPERATOR@EXAMPLE.COM', 'id']);

test('can cancel a search without changing permissions', function () {
    $user = User::factory()->create();

    $this->artisan('users:admin')->expectsSearch('Select a user to grant administrator access', 'cancel', 'no match', ['cancel' => 'Cancel without changes'])
        ->expectsOutput('Cancelled. No permissions changed.')->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('cannot grant administrator access through profile updates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email, 'is_admin' => true])->assertRedirect();

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('new registrations cannot set administrator access', function () {
    $this->post(route('register.store'), [
        'name' => 'Normal user', 'email' => 'normal@example.com',
        'password' => 'password', 'password_confirmation' => 'password', 'is_admin' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('users', ['email' => 'normal@example.com', 'is_admin' => false]);
});

test('search can revoke access from an account outside the recent users table', function () {
    $user = User::factory()->admin()->create(['email' => 'older@example.com']);
    User::factory()->count(21)->create();

    $this->artisan('users:admin', ['--revoke' => true])
        ->expectsSearch('Select a user to revoke administrator access', $user->id, 'older@example.com', [
            'cancel' => 'Cancel without changes',
            $user->id => "#{$user->id} | {$user->name} | {$user->email} | admin",
        ])->assertSuccessful();

    expect($user->fresh()->is_admin)->toBeFalse();
});
