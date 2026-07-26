<?php

use App\Models\User;
use App\Services\Soap\CommonsService;
use App\Services\Soap\RegistrationsService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Submit_PersonNewRegistration only accepts users on the federation's own list
 * (Get_DEC_UserList). Anyone else is certain to be refused, so they must be
 * told before they pay - never charged for a submission that cannot succeed.
 */
beforeEach(function () {
    fakeFederationDatabase();

    $this->mock(CommonsService::class, function ($mock) {
        foreach ([
            'getDisciplineList', 'getCategoryList', 'getCityList',
            'getCountryList', 'getGenderList', 'getVisaCategoryList',
        ] as $method) {
            $mock->shouldReceive($method)->andReturn([]);
        }
    });
});

function mockAcceptedUserIds(array $ids): void
{
    test()->mock(RegistrationsService::class, function ($mock) use ($ids) {
        $mock->shouldReceive('acceptedUserIds')->andReturn($ids);
        $mock->shouldReceive('acceptsUserId')
            ->andReturnUsing(fn (?int $id) => $id !== null && in_array($id, $ids, true));
    });
}

test('an accepted account sees no warning and can submit', function () {
    $user = User::factory()->create(['federation_user_id' => 6415]);
    mockAcceptedUserIds([6415]);

    $this->actingAs($user)->get(route('rider.registration'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rider/registration')
            ->where('submissionBlockedReason', null)
        );
});

test('an unaccepted account is warned before filling the form', function () {
    $user = User::factory()->create(['federation_user_id' => 7157]);
    mockAcceptedUserIds([6415]);

    $this->actingAs($user)->get(route('rider.registration'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissionBlockedReason', fn ($reason) => str_contains((string) $reason, 'not authorised'))
        );
});

test('an unlinked account is told to sign in with its portal account', function () {
    $user = User::factory()->create(['federation_user_id' => null]);
    mockAcceptedUserIds([6415]);

    $this->actingAs($user)->get(route('rider.registration'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('submissionBlockedReason', fn ($reason) => str_contains((string) $reason, 'not linked'))
        );
});

test('no payment is created for an account the federation will refuse', function () {
    $user = User::factory()->create(['federation_user_id' => 7157]);
    mockAcceptedUserIds([6415]);

    // The guard must fire before any payment session exists.
    $this->mock(App\Services\PayTabsService::class, function ($mock) {
        $mock->shouldNotReceive('createRiderRegistrationPayment');
    });

    $response = $this->actingAs($user)
        ->from(route('rider.registration'))
        ->post(route('rider.register'), [
            'first_name' => 'Test', 'last_name' => 'Rider', 'date_of_birth' => '1997-07-03',
            'gender_id' => '2', 'nationality_id' => '3', 'city_id' => '5', 'country_id' => '143',
            'email' => 'rider@test.ae', 'mobile' => '+971501234567', 'address' => 'Somewhere',
            'po_box' => 'PO Box 1', 'weight' => '70', 'visa_category' => '1',
            'eid' => '784-1997-4567891-7', 'register_season' => true, 'register_fei' => false,
            'passport_number' => 'P1', 'discipline_id' => '2', 'category_id' => '9',
        ]);

    $response->assertSessionHasErrors('payment');
    expect(App\Models\RiderRegistration::count())->toBe(0);
});
