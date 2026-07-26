<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        Fortify::authenticateUsing(function (Request $request) {
            $email = (string) $request->input('email');
            $password = (string) $request->input('password');

            try {
                $profile = UserProfile::findByEmail($email);
            } catch (\Throwable $e) {
                Log::error('Federation profile lookup failed during login', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);

                return $this->authenticateLocallyWhenDegraded($email, $password);
            }

            if (!$profile || !$profile->verifyPassword($password)) {
                return null;
            }

            return $this->mirrorProfileToLocalUser($profile, $email, $password);
        });
    }

    /**
     * eefsys.WebUserProfile is the account of record. The local users row is a
     * mirror kept in step on each successful login, and is provisioned on first
     * login so portal accounts that predate this application can sign in.
     */
    private function mirrorProfileToLocalUser(UserProfile $profile, string $email, string $password): User
    {
        $user = User::where('email', $email)->first() ?? new User(['email' => $email]);

        $user->fill([
            'name' => $profile->fullName() ?: ($user->name ?: $email),
            'password' => $password,
        ]);

        // Set the federation id once and leave it alone. A portal UserId does
        // not change, and overwriting it on every login silently undid any
        // explicit link made with `federation:link` - the account would appear
        // authorised until its next sign-in.
        $profileUserId = (int) $profile->UserId;

        if ($user->federation_user_id === null && $profileUserId > 0) {
            $user->federation_user_id = $profileUserId;
        }

        // The federation portal owns email verification for these accounts.
        $user->email_verified_at ??= now();
        $user->save();

        return $user;
    }

    /**
     * Fallback for when MSSQL is unreachable. Disabled by default: without the
     * federation profile there is nothing to verify against, so logins fail
     * closed unless an operator opts in for local development.
     */
    private function authenticateLocallyWhenDegraded(string $email, string $password): ?User
    {
        if (!config('services.federation.allow_local_auth_fallback', false)) {
            return null;
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }

        Log::warning('Authenticated against local users table - federation profile unavailable', [
            'email' => $email,
        ]);

        return $user;
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
