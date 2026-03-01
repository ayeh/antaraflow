<?php

declare(strict_types=1);

use App\Domain\API\Controllers\V1\ActionItemApiController;
use App\Domain\API\Controllers\V1\MeetingApiController;
use App\Domain\API\Middleware\ApiKeyAuthentication;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(ApiKeyAuthentication::class)->group(function () {
    Route::get('meetings', [MeetingApiController::class, 'index']);
    Route::get('meetings/{id}', [MeetingApiController::class, 'show']);
    Route::get('action-items', [ActionItemApiController::class, 'index']);
});
