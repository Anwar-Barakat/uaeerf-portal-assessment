<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * The federation row in eefsys.WebUserProfile is the account of record, so
     * it is written first. If that write fails, registration fails - a local
     * user with no federation profile cannot register a rider or enter a class.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'phone' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string'],
            'country' => ['nullable', 'string'],
        ])->validate();

        $federationUserId = $this->createFederationProfile($input);

        try {
            return User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'federation_user_id' => $federationUserId,
            ]);
        } catch (\Throwable $e) {
            UserProfile::where('UserId', $federationUserId)->delete();

            throw $e;
        }
    }

    /**
     * Insert into eefsys.WebUserProfile and return the allocated UserId.
     *
     * UserId is the primary key but has no identity or sequence, so it is
     * allocated under a row lock inside the MSSQL transaction.
     *
     * @param  array<string, string>  $input
     */
    protected function createFederationProfile(array $input): int
    {
        try {
            return UserProfile::transaction(function () use ($input) {
                if (UserProfile::findByEmail($input['email'])) {
                    throw ValidationException::withMessages([
                        'email' => 'This email already has a federation portal account.',
                    ]);
                }

                [$first, $last] = $this->splitName($input['name']);

                $userId = UserProfile::nextUserId();

                UserProfile::create([
                    'UserId' => $userId,
                    'Email' => $input['email'],
                    // Never store plain text, even though legacy portal rows do.
                    'Password' => Hash::make($input['password']),
                    'Fname' => $first,
                    'Lname' => $last,
                    'MobileNo' => $input['phone'],
                    'DateRegistered' => now(),
                    'IsSynced' => false,
                    'IsStableAuthorized' => false,
                ]);

                Log::info('Federation profile created', [
                    'email' => $input['email'],
                    'federation_user_id' => $userId,
                ]);

                return $userId;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Federation profile creation failed', [
                'email' => $input['email'],
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'email' => 'Registration is temporarily unavailable. Please try again shortly.',
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [
            mb_substr($parts[0] ?? $name, 0, 50),
            mb_substr($parts[1] ?? '', 0, 50),
        ];
    }
}
