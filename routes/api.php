<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(\App\Domain\API\Middleware\ApiKeyAuthentication::class)->group(function () {
    Route::get('meetings', [\App\Domain\API\Controllers\V1\MeetingApiController::class, 'index']);
});
