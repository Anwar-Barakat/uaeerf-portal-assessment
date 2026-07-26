<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());

    fakeFederationDatabase();
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '+971501234567',
        'city' => 'Dubai',
        'country' => 'AE',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
});

test('registration writes the federation profile and links its user id', function () {
    $this->post(route('register.store'), [
        'name' => 'Travis Cardenas',
        'email' => 'travis@example.com',
        'phone' => '+971501234567',
        'city' => 'Dubai',
        'country' => 'AE',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $profile = DB::connection('federation')->table('WebUserProfile')
        ->where('Email', 'travis@example.com')
        ->first();

    expect($profile)->not->toBeNull()
        ->and($profile->Fname)->toBe('Travis')
        ->and($profile->Lname)->toBe('Cardenas')
        ->and($profile->MobileNo)->toBe('+971501234567')
        // Never store plain text, even though legacy portal rows do.
        ->and($profile->Password)->not->toBe('Password123!');

    expect(User::where('email', 'travis@example.com')->value('federation_user_id'))
        ->toBe((int) $profile->UserId);
});

test('registration allocates sequential federation user ids', function () {
    DB::connection('federation')->table('WebUserProfile')->insert([
        'UserId' => 7155,
        'Email' => 'existing@example.com',
    ]);

    $this->post(route('register.store'), [
        'name' => 'Next User',
        'email' => 'next@example.com',
        'phone' => '+971501234567',
        'city' => 'Dubai',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    expect(User::where('email', 'next@example.com')->value('federation_user_id'))->toBe(7156);
});

test('registration fails and creates no local user when the federation write fails', function () {
    Schema::connection('federation')->drop('WebUserProfile');

    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Orphan User',
        'email' => 'orphan@example.com',
        'phone' => '+971501234567',
        'city' => 'Dubai',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(User::where('email', 'orphan@example.com')->exists())->toBeFalse();
});

test('registration is rejected when the portal already has the email', function () {
    DB::connection('federation')->table('WebUserProfile')->insert([
        'UserId' => 5000,
        'Email' => 'portal@example.com',
    ]);

    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Portal User',
        'email' => 'portal@example.com',
        'phone' => '+971501234567',
        'city' => 'Dubai',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(User::where('email', 'portal@example.com')->exists())->toBeFalse();
});

test('registration requires phone', function () {
    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'nophone@example.com',
        'city' => 'Dubai',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('phone');
    $this->assertGuest();
    expect(User::where('email', 'nophone@example.com')->exists())->toBeFalse();
});

test('registration requires city', function () {
    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'nocity@example.com',
        'phone' => '+971501234567',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('city');
    $this->assertGuest();
    expect(User::where('email', 'nocity@example.com')->exists())->toBeFalse();
});

test('registration requires matching password confirmation', function () {
    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'mismatch@example.com',
        'phone' => '+971501234567',
        'city' => 'Dubai',
        'password' => 'Password123!',
        'password_confirmation' => 'Different123!',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertGuest();
});

test('registration requires unique email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'taken@example.com',
        'phone' => '+971501234567',
        'city' => 'Dubai',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('country is optional and defaults are accepted', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'No Country',
        'email' => 'nocountry@example.com',
        'phone' => '+971501234567',
        'city' => 'Dubai',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $this->assertAuthenticated();
    expect(User::where('email', 'nocountry@example.com')->exists())->toBeTrue();
});
