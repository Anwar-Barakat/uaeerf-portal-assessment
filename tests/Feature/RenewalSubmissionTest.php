<?php

use App\Data\RiderRenewalData;
use App\Http\Controllers\RiderController;
use App\Models\RiderRenewal;
use App\Models\User;
use App\Services\Soap\RegistrationsService;

/**
 * Submit_PersonRenewal rejects a submission outright unless several fields the
 * form did not originally capture are present. Each was found by probing the
 * live service, so they are pinned here:
 *
 *   UserId omitted               -> "Invalid User ID"
 *   DivisionID omitted           -> "Invalid Division ID"
 *   Weight omitted               -> "Please enter a valid weight in kilogram"
 *   Complevel omitted (jumping)  -> "Invalid Category", then a failure to
 *                                   deserialise "" into an Integer
 *   JumpingDivisionLevel omitted -> "Invalid DIvision Level Level"
 */
function captureRenewalPayload(): object
{
    $captured = new stdClass();
    $captured->payload = null;

    test()->mock(RegistrationsService::class, function ($mock) use ($captured) {
        $mock->shouldReceive('submitPersonRenewal')
            ->andReturnUsing(function (array $payload) use ($captured) {
                $captured->payload = $payload;

                return (object) [
                    'Submit_PersonRenewalResult' => true,
                    'msg' => (object) ['string' => []],
                ];
            });
    });

    return $captured;
}

function makePendingRenewal(array $overrides = []): RiderRenewal
{
    $user = User::factory()->create(['federation_user_id' => 6336]);

    return RiderRenewal::create(array_merge([
        'user_id' => $user->id,
        'cart_id' => 'rider_renewal_' . $user->id . '_test',
        'rider_id' => 'E0008215',
        'season_id' => 15,
        'discipline_id' => 1,
        'weight' => '70',
        'status' => 'pending_payment',
    ], $overrides));
}

test('the renewal submits every field the service requires', function () {
    $captured = captureRenewalPayload();
    $renewal = makePendingRenewal();

    $fulfilled = app(RiderController::class)->processRenewal($renewal->cart_id, 'TST0000000009');

    expect($fulfilled)->toBeTrue()
        // The federation user id, not the local users.id.
        ->and($captured->payload['UserId'])->toBe(6336)
        ->and($captured->payload['DivisionID'])->toBe(1)
        ->and($captured->payload['PersonID'])->toBe('E0008215')
        ->and($captured->payload['SeasonCode'])->toBe(15)
        ->and($captured->payload['Weight'])->toBe('70')
        ->and($captured->payload['ReferenceNumber'])->toBe('TST0000000009');

    expect($renewal->fresh()->status)->toBe('completed');
});

test('show jumping renewals carry the division and competition level', function () {
    $captured = captureRenewalPayload();
    $renewal = makePendingRenewal([
        'discipline_id' => RiderRenewalData::DISCIPLINE_SHOW_JUMPING,
        'jumping_division_level' => 1,
        'competition_level' => '9',
    ]);

    app(RiderController::class)->processRenewal($renewal->cart_id, 'TST0000000010');

    expect($captured->payload['JumpingDivisionLevel'])->toBe(1)
        ->and($captured->payload['Complevel'])->toBe('9');
});

test('a rider who cannot be renewed is refused before any payment', function () {
    $user = User::factory()->create(['federation_user_id' => 6336]);

    $this->mock(RegistrationsService::class, function ($mock) {
        $mock->shouldReceive('renewalBlockedReason')
            ->andReturn('Athelete already register for the season 2022-2023');
    });

    // The guard must fire before a payment session exists.
    $this->mock(App\Services\PayTabsService::class, function ($mock) {
        $mock->shouldNotReceive('createRiderRenewalPayment');
    });

    $response = $this->actingAs($user)
        ->from(route('rider.renewal'))
        ->post(route('rider.renew'), [
            'rider_id' => 'E0002770',
            'season_id' => 14,
            'discipline_id' => 7,
            'weight' => '70',
        ]);

    $response->assertSessionHasErrors('rider_id');
    expect(session('errors')->first('rider_id'))->toContain('not been charged');
    expect(RiderRenewal::count())->toBe(0);
});

test('an unavailable eligibility check does not block a legitimate renewal', function () {
    $user = User::factory()->create(['federation_user_id' => 6336]);

    $this->mock(RegistrationsService::class, function ($mock) {
        $mock->shouldReceive('renewalBlockedReason')->andThrow(new RuntimeException('WS down'));
    });

    $this->mock(App\Services\PayTabsService::class, function ($mock) {
        $mock->shouldReceive('createRiderRenewalPayment')
            ->andReturn(['tran_ref' => 'TST1', 'redirect_url' => 'https://secure.paytabs.com/x']);
    });

    $this->actingAs($user)->post(route('rider.renew'), [
        'rider_id' => 'E0008215',
        'season_id' => 15,
        'discipline_id' => 1,
        'weight' => '70',
    ]);

    expect(RiderRenewal::count())->toBe(1);
});

test('the renewal form offers only riders the federation would accept', function () {
    $user = User::factory()->create(['federation_user_id' => 6336]);

    $this->mock(App\Services\Soap\CommonsService::class, function ($mock) {
        foreach ([
            'getSeasonList', 'getDisciplineList',
            'getJumpingDivisionLevelList', 'getCategoryList',
        ] as $method) {
            $mock->shouldReceive($method)->andReturn([]);
        }
    });

    $this->mock(App\Services\Soap\RidersService::class, function ($mock) {
        $mock->shouldReceive('ridersForStable')->andReturn([]);
        $mock->shouldReceive('allRenewableRiders')->andReturn([
            ['rider_id' => 'E0002043', 'name' => 'In Flight', 'stable_id' => 'E0000403', 'dob' => null, 'nationality' => null, 'registered_current_season' => false],
            ['rider_id' => 'E0009394', 'name' => 'Already Registered', 'stable_id' => 'E0000403', 'dob' => null, 'nationality' => null, 'registered_current_season' => true],
            ['rider_id' => 'E0009999', 'name' => 'Renewable', 'stable_id' => 'E0000403', 'dob' => null, 'nationality' => null, 'registered_current_season' => false],
        ]);
        // An application belonging to *any* portal account blocks the rider.
        $mock->shouldReceive('ridersWithRenewalInFlight')->andReturn(['E0002043']);
        $mock->shouldReceive('seasonsByRider')->andReturn(['E0009394' => [13], 'E0009999' => [11]]);
        $mock->shouldReceive('currentSeasonCode')->andReturn(13);
        $mock->shouldReceive('renewalBlocker')->passthru();
    });

    $this->actingAs($user)->get(route('rider.renewal'))
        ->assertOk()
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('rider/renewal')
            ->has('riders', 1)
            ->where('riders.0.rider_id', 'E0009999')
            ->where('hiddenRiders', 2)
        );
});

test('a rider with an application from another portal account is refused', function () {
    $user = User::factory()->create(['federation_user_id' => 6336]);

    $this->mock(App\Services\Soap\RidersService::class, function ($mock) {
        // Submitted by user 6422, not by the signed-in user. Get_PersonRenewal
        // would not report it, which is why this comes from the view instead.
        $mock->shouldReceive('ridersWithRenewalInFlight')->andReturn(['E0020211']);
        $mock->shouldReceive('seasonsByRider')->andReturn([]);
        $mock->shouldReceive('currentSeasonCode')->andReturn(13);
        $mock->shouldReceive('renewalBlocker')->passthru();
    });

    $this->mock(App\Services\PayTabsService::class, function ($mock) {
        $mock->shouldNotReceive('createRiderRenewalPayment');
    });

    $this->actingAs($user)
        ->from(route('rider.renewal'))
        ->post(route('rider.renew'), [
            'rider_id' => 'E0020211',
            'season_id' => 13,
            'discipline_id' => 1,
            'weight' => '70',
        ])
        ->assertSessionHasErrors('rider_id');

    expect(session('errors')->first('rider_id'))->toContain('already in progress');
    expect(RiderRenewal::count())->toBe(0);
});

test('weight is required for every discipline', function () {
    $rules = RiderRenewalData::rules();

    expect($rules['weight'])->toContain('required');
});

test('show jumping requires a division level and category, other disciplines do not', function () {
    $rules = RiderRenewalData::rules();

    expect($rules['jumping_division_level'])->toContain('required_if:discipline_id,2')
        ->and($rules['competition_level'])->toContain('required_if:discipline_id,2');
});
