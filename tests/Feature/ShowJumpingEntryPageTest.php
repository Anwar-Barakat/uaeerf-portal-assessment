<?php

use App\Models\User;
use App\Services\Soap\CalendarService;
use App\Services\Soap\HorsesService;
use App\Services\Soap\RidersService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The entry page previously shipped with hardcoded empty rider and horse lists,
 * which left the submit button permanently disabled and the flow unreachable.
 */
beforeEach(function () {
    fakeFederationDatabase();
});

function signInWithStable(?string $stableId = 'E0000403'): User
{
    $user = User::factory()->create(['federation_user_id' => 6001]);

    fakeFederationProfile($user->email, userId: 6001);

    if ($stableId !== null) {
        Illuminate\Support\Facades\DB::connection('federation')
            ->table('WebUserProfile')
            ->where('UserId', 6001)
            ->update(['Stable_ID' => $stableId]);
    }

    return $user;
}

function mockFederationLists(array $riders = [], array $horses = [], array $events = []): void
{
    test()->mock(RidersService::class, function ($m) use ($riders) {
        // Current-season registrations lead the list; the stable's own follow.
        $m->shouldReceive('ridersForCurrentSeason')->andReturn($riders);
        $m->shouldReceive('ridersForStable')->andReturn([]);
    });
    test()->mock(HorsesService::class, function ($m) use ($horses) {
        $m->shouldReceive('horsesForCurrentSeason')->andReturn($horses);
        $m->shouldReceive('searchHorses')->andReturn([]);
    });
    test()->mock(CalendarService::class, function ($m) use ($events) {
        $m->shouldReceive('getOpenEvents')->andReturn($events);
        // Each event is decorated with its own classes.
        $m->shouldReceive('getClassesForEvent')->andReturn([
            ['class_id' => 1, 'name' => 'One Round Against the Clock', 'entry_fee' => 150.0, 'date' => '2026-03-20'],
        ]);
    });
}

test('entry page lists riders, horses and open events for the stable', function () {
    $user = signInWithStable();

    mockFederationLists(
        riders: [['rider_id' => 'E0007711', 'name' => 'Test Rider', 'registered_current_season' => true]],
        horses: [['horse_id' => 'H000103', 'name' => 'Test Horse', 'at_rest' => false, 'registered_current_season' => true]],
        events: [['event_id' => '0004289', 'name' => 'Sharjah', 'location' => 'Sharjah', 'closes_at' => null]],
    );

    $this->actingAs($user)->get(route('jumping.entry'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('jumping/entry')
            ->where('stableId', 'E0000403')
            ->has('userRiders', 1)
            ->has('userHorses', 1)
            ->has('events', 1)
            ->has('events.0.classes', 1)
            ->where('events.0.classes.0.class_id', 1)
            // Alphanumeric federation ids must survive as strings.
            ->where('userRiders.0.rider_id', 'E0007711')
            ->where('userHorses.0.horse_id', 'H000103')
        );
});

test('entry page reports an unlinked profile instead of an unusable form', function () {
    $user = signInWithStable(stableId: null);

    mockFederationLists(events: [['event_id' => '1', 'name' => 'E', 'location' => '', 'closes_at' => null]]);

    $this->actingAs($user)->get(route('jumping.entry'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('stableId', '')
            ->has('userRiders', 0)
            ->has('userHorses', 0)
        );
});

test('one unavailable federation list does not blank the page', function () {
    $user = signInWithStable();

    $this->mock(RidersService::class, function ($m) {
        $m->shouldReceive('ridersForCurrentSeason')->andThrow(new RuntimeException('WSRiders down'));
        $m->shouldReceive('ridersForStable')->andThrow(new RuntimeException('WSRiders down'));
    });
    $this->mock(HorsesService::class, function ($m) {
        $m->shouldReceive('horsesForCurrentSeason')->andReturn([
            ['horse_id' => 'H000103', 'name' => 'Test Horse', 'at_rest' => false, 'registered_current_season' => true],
        ]);
        $m->shouldReceive('searchHorses')->andReturn([]);
    });
    $this->mock(CalendarService::class, fn ($m) => $m->shouldReceive('getOpenEvents')
        ->andThrow(new RuntimeException('WSCalendar down')));

    $this->actingAs($user)->get(route('jumping.entry'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('userRiders', 0)
            ->has('userHorses', 1)
            ->has('events', 0)
        );
});
