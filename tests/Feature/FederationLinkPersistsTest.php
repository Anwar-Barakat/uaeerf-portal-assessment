<?php

use App\Models\User;

/**
 * An explicit link made with `federation:link` must survive the next login.
 *
 * Login mirrors the portal profile onto the local user. It used to overwrite
 * federation_user_id every time, which silently reverted a deliberate link -
 * the account looked authorised until the user signed in again, then started
 * being refused with no visible cause.
 */
beforeEach(function () {
    fakeFederationDatabase();
});

test('login does not overwrite an existing federation link', function () {
    $user = User::factory()->create([
        'email' => 'linked@test.ae',
        'password' => 'Password123!',
        // Linked to an accepted federation account by an operator.
        'federation_user_id' => 6336,
    ]);

    // The portal profile carries a different, self-created id.
    fakeFederationProfile('linked@test.ae', 'Password123!', userId: 7158);

    $this->post(route('login.store'), [
        'email' => 'linked@test.ae',
        'password' => 'Password123!',
    ]);

    $this->assertAuthenticated();
    expect($user->fresh()->federation_user_id)->toBe(6336);
});

test('login still sets the federation id when the user has none', function () {
    $user = User::factory()->create([
        'email' => 'unlinked@test.ae',
        'password' => 'Password123!',
        'federation_user_id' => null,
    ]);

    fakeFederationProfile('unlinked@test.ae', 'Password123!', userId: 7161);

    $this->post(route('login.store'), [
        'email' => 'unlinked@test.ae',
        'password' => 'Password123!',
    ]);

    $this->assertAuthenticated();
    expect($user->fresh()->federation_user_id)->toBe(7161);
});
