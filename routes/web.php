<?php

use App\Domain\Account\Controllers\Auth\LoginController;
use App\Domain\Account\Controllers\Auth\LogoutController;
use App\Domain\Account\Controllers\Auth\RegisterController;
use App\Domain\Account\Controllers\MemberController;
use App\Domain\Account\Controllers\OrganizationController;
use App\Domain\Account\Controllers\OrganizationSettingsController;
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

Route::get('/', function () {
    return view('welcome');
});

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
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('organizations', OrganizationController::class);
    Route::resource('organizations.members', MemberController::class)->only(['index', 'store', 'update', 'destroy'])->shallow();
    Route::get('organizations/{organization}/settings', [OrganizationSettingsController::class, 'edit'])->name('organizations.settings.edit');
    Route::put('organizations/{organization}/settings', [OrganizationSettingsController::class, 'update'])->name('organizations.settings.update');

    Route::resource('meetings', MeetingController::class);
    Route::post('meetings/{meeting}/finalize', [MeetingController::class, 'finalize'])->name('meetings.finalize');
    Route::post('meetings/{meeting}/approve', [MeetingController::class, 'approve'])->name('meetings.approve');
    Route::post('meetings/{meeting}/revert', [MeetingController::class, 'revert'])->name('meetings.revert');

    Route::get('action-items', [ActionItemDashboardController::class, 'index'])->name('action-items.dashboard');

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
    });
});
