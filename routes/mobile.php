<?php

declare(strict_types=1);

use App\Domain\API\Controllers\Mobile\V1\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API (v1)
|--------------------------------------------------------------------------
|
| Serves the Flutter app. Kept apart from routes/api.php because the two have
| opposite tenancy semantics: that layer authenticates an organisation via an
| API key and scopes every query by hand, while this one authenticates a person
| through sanctum and lets the global OrganizationScope and the policies do the
| work. Mixing them in one prefix is how cross-tenant leaks happen.
|
| Prefix and name are applied in bootstrap/app.php: /api/mobile/v1, "mobile.".
|
*/

Route::middleware(['mobile.version'])->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:6,1')->name('login');
        Route::post('social', [AuthController::class, 'social'])
            ->middleware('throttle:6,1')->name('social');
        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:6,1')->name('password.forgot');
    });

    // Identity endpoints stop at authentication: someone who has been removed
    // from every organisation still needs to sign out and read their profile.
    Route::middleware('auth:sanctum')->prefix('auth')->name('auth.')->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('organization', [AuthController::class, 'switchOrganization'])->name('organization');
    });

    // Everything that touches tenant data.
    Route::middleware(['auth:sanctum', 'mobile.org', 'org.suspended'])->group(function () {
        require __DIR__.'/mobile/data.php';

        /*
         * Channel authorisation for the app's websocket connection. The
         * framework's own /broadcasting/auth sits behind the web middleware and
         * expects a session cookie, which a phone does not have; the callbacks
         * in routes/channels.php are shared, only the guard differs.
         */
        Route::post('broadcasting/auth', fn (Request $request) => Broadcast::auth($request))
            ->name('broadcasting.auth');
    });
});
