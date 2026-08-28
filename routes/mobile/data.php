<?php

declare(strict_types=1);

use App\Domain\API\Controllers\Mobile\V1\ActionItemController;
use App\Domain\API\Controllers\Mobile\V1\AiController;
use App\Domain\API\Controllers\Mobile\V1\AttendanceScanController;
use App\Domain\API\Controllers\Mobile\V1\AttendeeController;
use App\Domain\API\Controllers\Mobile\V1\BootstrapController;
use App\Domain\API\Controllers\Mobile\V1\CirculationController;
use App\Domain\API\Controllers\Mobile\V1\CommentController;
use App\Domain\API\Controllers\Mobile\V1\DeviceController;
use App\Domain\API\Controllers\Mobile\V1\DocumentController;
use App\Domain\API\Controllers\Mobile\V1\ExportController;
use App\Domain\API\Controllers\Mobile\V1\LiveSessionController;
use App\Domain\API\Controllers\Mobile\V1\MeetingController;
use App\Domain\API\Controllers\Mobile\V1\NotificationController;
use App\Domain\API\Controllers\Mobile\V1\ResolutionController;
use App\Domain\API\Controllers\Mobile\V1\SearchController;
use App\Domain\API\Controllers\Mobile\V1\SettingsController;
use App\Domain\API\Controllers\Mobile\V1\SyncController;
use App\Domain\API\Controllers\Mobile\V1\TranscriptionController;
use App\Domain\API\Controllers\Mobile\V1\VoiceNoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — tenant data
|--------------------------------------------------------------------------
|
| Loaded inside the auth:sanctum + mobile.org group in routes/mobile.php, so
| every route here has an authenticated user and a resolved organisation. The
| global OrganizationScope is therefore active: do not scope by hand.
|
*/

/*
 * Reads.
 */
Route::middleware('throttle:180,1')->group(function () {
    Route::get('bootstrap', BootstrapController::class)->name('bootstrap');

    Route::get('meetings/calendar', [MeetingController::class, 'calendar'])->name('meetings.calendar');
    Route::get('meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::get('meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::get('meetings/{meeting}/pack', [SyncController::class, 'pack'])->name('meetings.pack');

    Route::get('action-items', [ActionItemController::class, 'index'])->name('action-items.index');
    Route::get('action-items/{actionItem}', [ActionItemController::class, 'show'])->name('action-items.show');

    Route::get('meetings/{meeting}/attendees', [AttendeeController::class, 'index'])->name('attendees.index');
    Route::get('meetings/{meeting}/qr-registration', [AttendanceScanController::class, 'show'])->name('qr.show');

    Route::get('meetings/{meeting}/documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');

    Route::get('meetings/{meeting}/voice-notes', [VoiceNoteController::class, 'index'])->name('voice-notes.index');
    Route::get('meetings/{meeting}/comments', [CommentController::class, 'index'])->name('comments.index');
    Route::get('meetings/{meeting}/resolutions', [ResolutionController::class, 'index'])->name('resolutions.index');
    Route::get('meetings/{meeting}/exports', [ExportController::class, 'index'])->name('exports.index');

    Route::get('meetings/{meeting}/transcriptions', [TranscriptionController::class, 'index'])->name('transcriptions.index');
    Route::get('transcriptions/{transcription}', [TranscriptionController::class, 'show'])->name('transcriptions.show');
    Route::get('transcriptions/{transcription}/segments', [TranscriptionController::class, 'segments'])->name('transcriptions.segments');

    Route::get('live/{session}/state', [LiveSessionController::class, 'state'])->name('live.state');
    Route::get('live/join/{token}', [LiveSessionController::class, 'join'])->name('live.join');
    Route::get('live/{session}/participants', [LiveSessionController::class, 'participants'])->name('live.participants');

    Route::get('circulations/pending', [CirculationController::class, 'pending'])->name('circulations.pending');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');

    Route::get('settings/profile', [SettingsController::class, 'profile'])->name('settings.profile');
    Route::get('settings/notifications', [SettingsController::class, 'notificationPreferences'])->name('settings.notifications');

    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::get('sync/pull', [SyncController::class, 'pull'])->name('sync.pull');

    Route::get('meetings/{meeting}/extractions', [AiController::class, 'extractions'])->name('ai.extractions');
    Route::get('meetings/{meeting}/chat', [AiController::class, 'chatHistory'])->name('ai.chat.history');
    Route::get('meetings/{meeting}/prep-brief', [AiController::class, 'prepBrief'])->name('ai.prep-brief');
    Route::get('insights', [AiController::class, 'insights'])->name('ai.insights');
});

/*
 * Downloads render a document rather than JSON, so they sit apart from the
 * read group only for clarity — the limits are the same.
 */
Route::middleware('throttle:60,1')->group(function () {
    Route::get('meetings/{meeting}/export', [ExportController::class, 'download'])->name('exports.download');
});

/*
 * Writes. Idempotency-Key is honoured on every POST here, which is what stops a
 * retried offline queue from creating duplicates.
 */
Route::middleware(['throttle:60,1', 'mobile.idempotency'])->group(function () {
    Route::post('meetings', [MeetingController::class, 'store'])->name('meetings.store');
    Route::patch('meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
    Route::delete('meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');
    Route::post('meetings/{meeting}/finalize', [MeetingController::class, 'finalize'])->name('meetings.finalize');
    Route::post('meetings/{meeting}/approve', [MeetingController::class, 'approve'])->name('meetings.approve');

    Route::post('action-items', [ActionItemController::class, 'store'])->name('action-items.store');
    Route::patch('action-items/{actionItem}', [ActionItemController::class, 'update'])->name('action-items.update');
    Route::patch('action-items/{actionItem}/status', [ActionItemController::class, 'updateStatus'])->name('action-items.status');
    Route::delete('action-items/{actionItem}', [ActionItemController::class, 'destroy'])->name('action-items.destroy');
    Route::post('action-items/bulk', [ActionItemController::class, 'bulk'])->name('action-items.bulk');

    Route::post('meetings/{meeting}/documents', [DocumentController::class, 'store'])->name('documents.store');
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

    Route::post('meetings/{meeting}/voice-notes', [VoiceNoteController::class, 'store'])->name('voice-notes.store');
    Route::delete('voice-notes/{voiceNote}', [VoiceNoteController::class, 'destroy'])->name('voice-notes.destroy');

    Route::post('meetings/{meeting}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::patch('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

    Route::post('meetings/{meeting}/resolutions', [ResolutionController::class, 'store'])->name('resolutions.store');
    Route::patch('resolutions/{resolution}', [ResolutionController::class, 'update'])->name('resolutions.update');
    Route::delete('resolutions/{resolution}', [ResolutionController::class, 'destroy'])->name('resolutions.destroy');
    Route::post('resolutions/{resolution}/vote', [ResolutionController::class, 'vote'])->name('resolutions.vote');
    Route::post('resolutions/{resolution}/close', [ResolutionController::class, 'close'])->name('resolutions.close');

    Route::post('meetings/{meeting}/live/start', [LiveSessionController::class, 'start'])->name('live.start');
    Route::post('live/{session}/pause', [LiveSessionController::class, 'pause'])->name('live.pause');
    Route::post('live/{session}/resume', [LiveSessionController::class, 'resume'])->name('live.resume');
    Route::post('live/{session}/extraction', [LiveSessionController::class, 'extraction'])->name('live.extraction');
    Route::post('live/{session}/end', [LiveSessionController::class, 'end'])->name('live.end');
    Route::post('live/{session}/invite', [LiveSessionController::class, 'invite'])->name('live.invite');
    Route::post('live/{session}/bookmarks', [LiveSessionController::class, 'storeBookmark'])->name('live.bookmarks.store');
    Route::delete('live/bookmarks/{bookmark}', [LiveSessionController::class, 'destroyBookmark'])->name('live.bookmarks.destroy');

    Route::post('meetings/{meeting}/attendees', [AttendeeController::class, 'store'])->name('attendees.store');
    Route::patch('attendees/{attendee}/rsvp', [AttendeeController::class, 'updateRsvp'])->name('attendees.rsvp');
    Route::patch('attendees/{attendee}/presence', [AttendeeController::class, 'updatePresence'])->name('attendees.presence');
    Route::delete('attendees/{attendee}', [AttendeeController::class, 'destroy'])->name('attendees.destroy');

    Route::post('attendance/scan', [AttendanceScanController::class, 'scan'])->name('attendance.scan');
    Route::post('meetings/{meeting}/qr-registration', [AttendanceScanController::class, 'store'])->name('qr.store');
    Route::delete('meetings/{meeting}/qr-registration', [AttendanceScanController::class, 'destroy'])->name('qr.destroy');

    Route::post('circulations/{recipient}/respond', [CirculationController::class, 'respond'])->name('circulations.respond');

    Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
    Route::delete('devices/{deviceId}', [DeviceController::class, 'destroy'])->name('devices.destroy');

    Route::patch('notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    Route::patch('settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::post('settings/profile/avatar', [SettingsController::class, 'updateAvatar'])->name('settings.avatar');
    Route::patch('settings/notifications', [SettingsController::class, 'updateNotificationPreferences'])->name('settings.notifications.update');

    Route::post('sync/push', [SyncController::class, 'push'])->name('sync.push');
});

/*
 * AI costs money per call, so it gets a tighter allowance than ordinary writes.
 */
Route::middleware('throttle:20,1')->group(function () {
    Route::post('search/ai', [SearchController::class, 'ai'])->name('search.ai');
    Route::post('meetings/{meeting}/extract', [AiController::class, 'extract'])->name('ai.extract');
    Route::post('meetings/{meeting}/chat', [AiController::class, 'chat'])->name('ai.chat');
    Route::post('meetings/{meeting}/prep-brief/generate', [AiController::class, 'generatePrepBrief'])->name('ai.prep-brief.generate');
    Route::post('insights/{insight}/read', [AiController::class, 'readInsight'])->name('ai.insights.read');
    Route::post('insights/{insight}/dismiss', [AiController::class, 'dismissInsight'])->name('ai.insights.dismiss');

    Route::patch('transcriptions/{transcription}/speakers', [TranscriptionController::class, 'renameSpeaker'])->name('transcriptions.speakers');
});

/*
 * Chunk upload gets its own allowance. A 15-second cadence is 4 requests a
 * minute, but a client draining a backlog after a network drop legitimately
 * sends far more, and throttling that is how transcripts end up with holes.
 */
Route::middleware('throttle:120,1')->group(function () {
    Route::post('live/{session}/chunks', [LiveSessionController::class, 'chunk'])->name('live.chunk');
});
