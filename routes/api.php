<?php

declare(strict_types=1);

use App\Domain\API\Controllers\V1\ActionItemApiController;
use App\Domain\API\Controllers\V1\AnalyticsApiController;
use App\Domain\API\Controllers\V1\ApiInfoController;
use App\Domain\API\Controllers\V1\AttendeeApiController;
use App\Domain\API\Controllers\V1\CommentApiController;
use App\Domain\API\Controllers\V1\MeetingApiController;
use App\Domain\API\Controllers\V1\TranscriptionApiController;
use App\Domain\API\Controllers\V1\WebhookApiController;
use App\Domain\API\Middleware\ApiKeyAuthentication;
use Illuminate\Support\Facades\Route;

Route::get('v1', ApiInfoController::class);

Route::prefix('v1')->middleware([ApiKeyAuthentication::class, 'throttle:api'])->group(function () {
    Route::get('meetings', [MeetingApiController::class, 'index']);
    Route::post('meetings', [MeetingApiController::class, 'store']);
    Route::get('meetings/{id}', [MeetingApiController::class, 'show']);
    Route::patch('meetings/{id}', [MeetingApiController::class, 'update']);
    Route::delete('meetings/{id}', [MeetingApiController::class, 'destroy']);
    Route::get('action-items', [ActionItemApiController::class, 'index']);
    Route::post('action-items', [ActionItemApiController::class, 'store']);
    Route::patch('action-items/{id}', [ActionItemApiController::class, 'update']);
    Route::get('meetings/{id}/attendees', [AttendeeApiController::class, 'index']);
    Route::post('meetings/{id}/attendees', [AttendeeApiController::class, 'store']);
    Route::get('meetings/{id}/transcriptions', [TranscriptionApiController::class, 'index']);
    Route::get('meetings/{id}/transcriptions/{transcriptionId}', [TranscriptionApiController::class, 'show']);
    Route::get('meetings/{id}/comments', [CommentApiController::class, 'index']);
    Route::post('meetings/{id}/comments', [CommentApiController::class, 'store']);
    Route::put('comments/{commentId}', [CommentApiController::class, 'update']);
    Route::delete('comments/{commentId}', [CommentApiController::class, 'destroy']);
    Route::get('analytics/summary', [AnalyticsApiController::class, 'summary']);
    Route::get('webhooks', [WebhookApiController::class, 'index']);
    Route::post('webhooks', [WebhookApiController::class, 'store']);
    Route::delete('webhooks/{webhookEndpoint}', [WebhookApiController::class, 'destroy']);
});
