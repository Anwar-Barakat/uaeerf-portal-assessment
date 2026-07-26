<?php

use App\Models\RiderRegistration;
use App\Models\RiderRenewal;
use App\Models\ShowJumpingEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Detail pages carry personal data and payment references, so each one must be
 * scoped to its owner - another user's id has to 404, not render.
 */
function makeRegistrationFor(User $user): RiderRegistration
{
    return RiderRegistration::create([
        'user_id' => $user->id,
        'cart_id' => 'rider_reg_' . $user->id . '_' . bin2hex(random_bytes(4)),
        'rider_name' => 'Test Rider',
        'date_of_birth' => '1997-07-03',
        'nationality' => 'ARE',
        'discipline_id' => 2,
        'category_id' => 9,
        'status' => 'completed',
        'tran_ref' => 'TST0000000123',
    ]);
}

function makeRenewalFor(User $user): RiderRenewal
{
    return RiderRenewal::create([
        'user_id' => $user->id,
        'cart_id' => 'rider_renewal_' . $user->id . '_' . bin2hex(random_bytes(4)),
        'rider_id' => 'E0008215',
        'season_id' => 13,
        'discipline_id' => 1,
        'weight' => '70',
        'status' => 'completed',
        'tran_ref' => 'TST0000000124',
    ]);
}

function makeEntryFor(User $user): ShowJumpingEntry
{
    return ShowJumpingEntry::create([
        'user_id' => $user->id,
        'cart_id' => 'jumping_' . $user->id . '_' . bin2hex(random_bytes(4)),
        'rider_id' => 'S0000001',
        'horse_id' => 'S0000052',
        'event_id' => 4289,
        'class_id' => 1,
        'season_code' => 13,
        'event_name' => 'TEST - Sharjah',
        'status' => 'completed',
        'tran_ref' => 'TST0000000125',
    ]);
}

test('a registration detail page renders for its owner', function () {
    $user = User::factory()->create();
    $registration = makeRegistrationFor($user);

    $this->actingAs($user)->get("/history/registrations/{$registration->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('history/registration-show')
            ->where('registration.rider_name', 'Test Rider')
            ->where('registration.tran_ref', 'TST0000000123')
        );
});

test('a renewal detail page renders for its owner', function () {
    $user = User::factory()->create();
    $renewal = makeRenewalFor($user);

    $this->actingAs($user)->get("/history/renewals/{$renewal->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('history/renewal-show')
            ->where('renewal.rider_id', 'E0008215')
        );
});

test('an entry detail page renders for its owner', function () {
    $user = User::factory()->create();
    $entry = makeEntryFor($user);

    $this->actingAs($user)->get("/history/entries/{$entry->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('history/entry-show')
            ->where('entry.horse_id', 'S0000052')
        );
});

test('another user cannot open someone else\'s records', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $registration = makeRegistrationFor($owner);
    $renewal = makeRenewalFor($owner);
    $entry = makeEntryFor($owner);

    $this->actingAs($intruder)->get("/history/registrations/{$registration->id}")->assertNotFound();
    $this->actingAs($intruder)->get("/history/renewals/{$renewal->id}")->assertNotFound();
    $this->actingAs($intruder)->get("/history/entries/{$entry->id}")->assertNotFound();
});

test('detail pages require authentication', function () {
    $user = User::factory()->create();
    $registration = makeRegistrationFor($user);

    $this->get("/history/registrations/{$registration->id}")->assertRedirect(route('login'));
});
