<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors [EEF].[eefsys].[WebUserProfile]. UserId is the primary key and is not
 * an identity column on the live server, so it is supplied explicitly here too.
 */
beforeEach(function () {
    fakeFederationDatabase();
});

function createLocalUser(string $password = 'Password123!'): User
{
    return User::factory()->create([
        'email' => 'profile@test.ae',
        'password' => $password,
    ]);
}

function createProfile(string $password = 'Password123!', int $userId = 6001, bool $plainText = false): void
{
    fakeFederationProfile('profile@test.ae', $password, $userId, $plainText, 'Profile', 'Test');
}

test('login succeeds when federation profile matches', function () {
    createLocalUser();
    createProfile();

    $response = $this->post(route('login.store'), [
        'email' => 'profile@test.ae',
        'password' => 'Password123!',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('login denied when federation password mismatches', function () {
    createLocalUser();
    createProfile('DifferentPassword999!');

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => 'profile@test.ae',
        'password' => 'Password123!',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors();
});

test('login denied when federation profile is missing', function () {
    createLocalUser();

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => 'profile@test.ae',
        'password' => 'Password123!',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors();
});

test('legacy plain text portal password is accepted', function () {
    createLocalUser();
    createProfile('legacy-plain-pass', 6002, plainText: true);

    $this->post(route('login.store'), [
        'email' => 'profile@test.ae',
        'password' => 'legacy-plain-pass',
    ]);

    $this->assertAuthenticated();
});

test('portal account with no local user is provisioned on first login', function () {
    createProfile(userId: 6003);

    expect(User::where('email', 'profile@test.ae')->exists())->toBeFalse();

    $this->post(route('login.store'), [
        'email' => 'profile@test.ae',
        'password' => 'Password123!',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'profile@test.ae')->first();
    expect($user)->not->toBeNull()
        ->and($user->federation_user_id)->toBe(6003)
        ->and($user->name)->toBe('Profile Test');
});

test('successful login stores the federation user id on the local user', function () {
    createLocalUser();
    createProfile(userId: 6004);

    $this->post(route('login.store'), [
        'email' => 'profile@test.ae',
        'password' => 'Password123!',
    ]);

    $this->assertAuthenticated();
    expect(User::where('email', 'profile@test.ae')->value('federation_user_id'))->toBe(6004);
});

test('login fails closed when the federation database is unreachable', function () {
    createLocalUser();
    Schema::connection('federation')->drop('WebUserProfile');

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => 'profile@test.ae',
        'password' => 'Password123!',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors();
});

test('local fallback authenticates when explicitly enabled and mssql is down', function () {
    createLocalUser();
    config()->set('services.federation.allow_local_auth_fallback', true);
    Schema::connection('federation')->drop('WebUserProfile');

    $this->post(route('login.store'), [
        'email' => 'profile@test.ae',
        'password' => 'Password123!',
    ]);

    $this->assertAuthenticated();
});
