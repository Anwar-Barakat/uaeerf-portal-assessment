<?php

use App\Data\RiderRegistrationData;
use App\Data\RiderRenewalData;
use App\Models\User;
use App\Services\Soap\CommonsService;
use Illuminate\Support\Facades\Cache;

/**
 * The searchable selects are rendered from the federation's own lists, and some
 * options are disabled because the service is certain to refuse them. Neither
 * survives the trip to the server on its own: a form post can carry any value.
 * These rules are the server-side half of that guard.
 */
beforeEach(function () {
    Cache::flush();
});

test('a code the federation never offered is rejected', function () {
    Cache::put('soap_genders', [['Code' => 'M', 'Name' => 'Male'], ['Code' => 'F', 'Name' => 'Female']]);

    $rules = RiderRegistrationData::rules();

    $validator = Validator::make(['gender_id' => 'X'], ['gender_id' => $rules['gender_id']]);

    expect($validator->fails())->toBeTrue();

    expect(Validator::make(['gender_id' => 'M'], ['gender_id' => $rules['gender_id']])->fails())
        ->toBeFalse();
});

test('an unknown list does not block a submission', function () {
    // Nothing cached: the web service has never been reached in this process,
    // so there is no list to check against. Validation must not turn that into
    // "every value is invalid".
    $rules = RiderRegistrationData::rules();

    expect(Validator::make(['gender_id' => 'M'], ['gender_id' => $rules['gender_id']])->fails())
        ->toBeFalse();
});

test('every renewal select is checked against the federation list', function () {
    Cache::put('soap_seasons', [['Code' => 13, 'Name' => '2023-2024']]);
    Cache::put('soap_disciplines', [['Code' => 1, 'Name' => 'Endurance']]);

    $rules = RiderRenewalData::rules();

    $payload = ['rider_id' => 'E0008215', 'weight' => '70', 'season_id' => 99, 'discipline_id' => 1];

    expect(Validator::make($payload, $rules)->errors()->keys())->toBe(['season_id']);
});

test('a renewal for a season the form never offered is refused before payment', function () {
    Cache::put('soap_seasons', [['Code' => 13, 'Name' => '2023-2024']]);

    $user = User::factory()->create(['federation_user_id' => 6336]);

    $this->mock(App\Services\PayTabsService::class, function ($mock) {
        $mock->shouldNotReceive('createRiderRenewalPayment');
    });

    $this->actingAs($user)
        ->from(route('rider.renewal'))
        ->post(route('rider.renew'), [
            'rider_id' => 'E0008215',
            'season_id' => 99,
            'discipline_id' => 1,
            'weight' => '70',
        ])
        ->assertSessionHasErrors('season_id');

    expect(App\Models\RiderRenewal::count())->toBe(0);
});

test('allowed codes are deduplicated', function () {
    // The federation views hold one row per registration, so a list can carry
    // the same code several times.
    Cache::put('soap_cities', [
        ['Code' => '1', 'Name' => 'Abu Dhabi'],
        ['Code' => '1', 'Name' => 'Abu Dhabi'],
        ['Code' => '2', 'Name' => 'Dubai'],
    ]);

    expect(app(CommonsService::class)->allowedCodes('cities'))->toBe(['1', '2']);
});
