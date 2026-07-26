<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('history/registrations', [\App\Http\Controllers\HistoryController::class, 'registrations'])->name('history.registrations');
    Route::get('history/renewals', [\App\Http\Controllers\HistoryController::class, 'renewals'])->name('history.renewals');
    Route::get('history/entries', [\App\Http\Controllers\HistoryController::class, 'entries'])->name('history.entries');

    // Detail pages
    Route::get('history/registrations/{id}', [\App\Http\Controllers\HistoryController::class, 'showRegistration'])
        ->whereNumber('id')->name('history.registrations.show');
    Route::get('history/renewals/{id}', [\App\Http\Controllers\HistoryController::class, 'showRenewal'])
        ->whereNumber('id')->name('history.renewals.show');
    Route::get('history/entries/{id}', [\App\Http\Controllers\HistoryController::class, 'showEntry'])
        ->whereNumber('id')->name('history.entries.show');

    // Rider Registration & Renewal Pages
    Route::get('rider/registration', [\App\Http\Controllers\RiderController::class, 'create'])
        ->name('rider.registration');

    Route::get('rider/renewal', [\App\Http\Controllers\RiderController::class, 'renewalForm'])
        ->name('rider.renewal');

    // Show Jumping Entry Page
    Route::get('jumping/entry', [\App\Http\Controllers\ShowJumpingController::class, 'create'])
        ->name('jumping.entry');

    // API Endpoints (rate limited: 10 requests per minute)
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('rider/register', [\App\Http\Controllers\RiderController::class, 'initiateRegistration'])->name('rider.register');
        Route::post('rider/renew', [\App\Http\Controllers\RiderController::class, 'initiateRenewal'])->name('rider.renew');
        Route::post('jumping/validate', [\App\Http\Controllers\ShowJumpingController::class, 'validateEligibility'])->name('jumping.validate');
        Route::post('jumping/entry', [\App\Http\Controllers\ShowJumpingController::class, 'initiateEntry'])->name('jumping.entry.submit');
    });
});

require __DIR__.'/settings.php';
