<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Stand in for [EEF].[eefsys].[WebUserProfile] on an in-memory sqlite database.
 *
 * Login and registration both go through the federation profile, so any test
 * that authenticates needs this. sqlite cannot create a schema-qualified table,
 * hence the unqualified override.
 */
function fakeFederationDatabase(): void
{
    config()->set('database.connections.federation', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    config()->set('services.federation.user_profile_table', 'WebUserProfile');
    config()->set('services.federation.allow_local_auth_fallback', false);

    Illuminate\Support\Facades\Schema::connection('federation')->create('WebUserProfile', function ($table) {
        $table->integer('UserId')->primary();
        $table->string('Email')->nullable();
        $table->string('Password', 250)->nullable();
        $table->string('Fname', 50)->nullable();
        $table->string('Lname', 50)->nullable();
        $table->string('MobileNo')->nullable();
        $table->dateTime('Dob')->nullable();
        $table->string('Stable_ID')->nullable();
        $table->string('Discipline', 50)->nullable();
        $table->integer('UserGender')->nullable();
        $table->boolean('IsStableAuthorized')->nullable();
        $table->boolean('IsSynced')->nullable();
        $table->dateTime('DateRegistered')->nullable();
        $table->string('AccountsCustomerID')->nullable();
    });
}

/**
 * Insert a federation profile row. Legacy portal rows hold the password in
 * plain text; rows this application writes are bcrypt.
 */
function fakeFederationProfile(
    string $email,
    string $password = 'password',
    int $userId = 6001,
    bool $plainText = false,
    string $fname = 'Test',
    string $lname = 'Account',
): int {
    Illuminate\Support\Facades\DB::connection('federation')->table('WebUserProfile')->insert([
        'UserId' => $userId,
        'Email' => $email,
        'Password' => $plainText ? $password : Illuminate\Support\Facades\Hash::make($password),
        'Fname' => $fname,
        'Lname' => $lname,
        'DateRegistered' => now(),
    ]);

    return $userId;
}
