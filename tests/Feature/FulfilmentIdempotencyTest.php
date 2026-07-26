<?php

use App\Http\Controllers\RiderController;
use App\Models\RiderRenewal;
use App\Models\User;
use App\Services\Soap\RegistrationsService;
use App\Services\Soap\RidersService;

/**
 * A renewal was filed with the federation, the local "completed" write hit
 * "database is locked", the job read that as a failure and submitted again -
 * and the user was told their paid renewal had not gone through, for a renewal
 * that had. The submission cannot be undone, so it must never be repeated and a
 * local write failure must never be mistaken for a federation failure.
 */
function makeRenewal(): RiderRenewal
{
    $user = User::factory()->create(['federation_user_id' => 6336]);

    return RiderRenewal::create([
        'user_id' => $user->id,
        'cart_id' => 'rider_renewal_' . $user->id . '_idem',
        'rider_id' => 'E0003488',
        'season_id' => 13,
        'discipline_id' => 1,
        'weight' => '123',
        'status' => 'pending_payment',
    ]);
}

test('a renewal already filed under this reference is not submitted again', function () {
    $renewal = makeRenewal();

    $this->mock(RidersService::class, function ($mock) {
        $mock->shouldReceive('renewalExistsForReference')->with('TST2620702720212')->andReturn(true);
    });

    $this->mock(RegistrationsService::class, function ($mock) {
        // The federation already has it; asking again would file a second
        // application for one payment.
        $mock->shouldNotReceive('submitPersonRenewal');
    });

    $fulfilled = app(RiderController::class)->processRenewal($renewal->cart_id, 'TST2620702720212');

    expect($fulfilled)->toBeTrue()
        ->and($renewal->fresh()->status)->toBe('completed');
});

test('"Reference number exist" is read as our own submission, not a rejection', function () {
    $renewal = makeRenewal();

    $this->mock(RidersService::class, function ($mock) {
        // The view has not caught up yet, so the service itself tells us.
        $mock->shouldReceive('renewalExistsForReference')->andReturn(false);
    });

    $this->mock(RegistrationsService::class, function ($mock) {
        $mock->shouldReceive('submitPersonRenewal')->once()->andReturn((object) [
            'Submit_PersonRenewalResult' => false,
            'msg' => (object) ['string' => [
                'Reference number exist.',
                'There is already a pending application for the Rider.',
            ]],
        ]);
    });

    $fulfilled = app(RiderController::class)->processRenewal($renewal->cart_id, 'TST2620702720212');

    expect($fulfilled)->toBeTrue()
        ->and($renewal->fresh()->status)->toBe('completed');
});

test('a genuine refusal is still recorded as a failure', function () {
    $renewal = makeRenewal();

    $this->mock(RidersService::class, function ($mock) {
        $mock->shouldReceive('renewalExistsForReference')->andReturn(false);
    });

    $this->mock(RegistrationsService::class, function ($mock) {
        $mock->shouldReceive('submitPersonRenewal')->once()->andReturn((object) [
            'Submit_PersonRenewalResult' => false,
            'msg' => (object) ['string' => ['Athelete already register for the season 2022-2023']],
        ]);
    });

    $fulfilled = app(RiderController::class)->processRenewal($renewal->cart_id, 'TST0000000099');

    expect($fulfilled)->toBeFalse()
        ->and($renewal->fresh()->status)->toBe('failed');
});
