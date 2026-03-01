<?php

use App\Domain\Account\Controllers\Auth\LoginController;
use App\Domain\Account\Controllers\Auth\LogoutController;
use App\Domain\Account\Controllers\Auth\RegisterController;
use App\Domain\Account\Controllers\MemberController;
use App\Domain\Account\Controllers\OrganizationController;
use App\Domain\Account\Controllers\OrganizationSettingsController;
use App\Domain\Account\Controllers\ProfileController;
use App\Domain\ActionItem\Controllers\ActionItemController;
use App\Domain\ActionItem\Controllers\ActionItemDashboardController;
use App\Domain\AI\Controllers\ChatController;
use App\Domain\AI\Controllers\ExtractionController;
use App\Domain\Attendee\Controllers\AttendeeController;
use App\Domain\Meeting\Controllers\ManualNoteController;
use App\Domain\Meeting\Controllers\MeetingController;
use App\Domain\Transcription\Controllers\TranscriptionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Guest routes
Route::get('/', function () {
    return view('welcome');
});

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LogoutController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', 'org.context'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::post('profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');

    // Organizations
    Route::resource('organizations', OrganizationController::class);
    Route::resource('organizations.members', MemberController::class)->only(['index', 'store', 'update', 'destroy'])->shallow();
    Route::get('organizations/{organization}/settings', [OrganizationSettingsController::class, 'edit'])->name('organizations.settings.edit');
    Route::put('organizations/{organization}/settings', [OrganizationSettingsController::class, 'update'])->name('organizations.settings.update');
    Route::post('organizations/{organization}/settings/logo', [OrganizationSettingsController::class, 'uploadLogo'])->name('organizations.settings.logo');

    // Meeting Templates
    Route::resource('meeting-templates', \App\Domain\Meeting\Controllers\MeetingTemplateController::class);

    // Meeting Series
    Route::resource('meeting-series', \App\Domain\Meeting\Controllers\MeetingSeriesController::class);
    Route::post('meeting-series/{meetingSeries}/generate', [\App\Domain\Meeting\Controllers\MeetingSeriesController::class, 'generateMeetings'])->name('meeting-series.generate');

    // Meetings
    Route::resource('meetings', MeetingController::class);
    Route::post('meetings/{meeting}/finalize', [MeetingController::class, 'finalize'])->name('meetings.finalize');
    Route::post('meetings/{meeting}/approve', [MeetingController::class, 'approve'])->name('meetings.approve');
    Route::post('meetings/{meeting}/revert', [MeetingController::class, 'revert'])->name('meetings.revert');

    // Cross-meeting dashboards
    Route::get('action-items', [ActionItemDashboardController::class, 'index'])->name('action-items.dashboard');

    // Meeting sub-resources (transcriptions, notes, attendees, actions, chat, extractions)
    Route::prefix('meetings/{meeting}')->as('meetings.')->group(function () {
        Route::resource('transcriptions', TranscriptionController::class)->only(['store', 'show', 'destroy']);
        Route::resource('manual-notes', ManualNoteController::class);
        Route::post('extract', [ExtractionController::class, 'extract'])->name('extract');
        Route::get('extractions', [ExtractionController::class, 'index'])->name('extractions.index');
        Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
        Route::post('chat', [ChatController::class, 'store'])->name('chat.store');
        Route::resource('action-items', ActionItemController::class);
        Route::post('action-items/{actionItem}/carry-forward', [ActionItemController::class, 'carryForward'])->name('action-items.carry-forward');

        Route::resource('attendees', AttendeeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('attendees/bulk-invite', [AttendeeController::class, 'bulkInvite'])->name('attendees.bulk-invite');
        Route::patch('attendees/{attendee}/rsvp', [AttendeeController::class, 'updateRsvp'])->name('attendees.rsvp');
        Route::patch('attendees/{attendee}/presence', [AttendeeController::class, 'markPresence'])->name('attendees.presence');

        Route::get('export/pdf', [\App\Domain\Export\Controllers\ExportController::class, 'pdf'])->name('export.pdf');
        Route::get('export/word', [\App\Domain\Export\Controllers\ExportController::class, 'word'])->name('export.word');
        Route::get('export/csv', [\App\Domain\Export\Controllers\ExportController::class, 'csv'])->name('export.csv');

        Route::get('shares', [\App\Domain\Collaboration\Controllers\ShareController::class, 'index'])->name('shares.index');
        Route::post('shares', [\App\Domain\Collaboration\Controllers\ShareController::class, 'store'])->name('shares.store');
        Route::delete('shares/{share}', [\App\Domain\Collaboration\Controllers\ShareController::class, 'destroy'])->name('shares.destroy');

        // Comments
        Route::post('comments', [\App\Domain\Collaboration\Controllers\CommentController::class, 'store'])->name('comments.store');
    });

    Route::put('comments/{comment}', [\App\Domain\Collaboration\Controllers\CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [\App\Domain\Collaboration\Controllers\CommentController::class, 'destroy'])->name('comments.destroy');
});
