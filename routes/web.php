<?php

use App\Domain\Account\Controllers\ApiKeySettingsController;
use App\Domain\Account\Controllers\Auth\LoginController;
use App\Domain\Account\Controllers\Auth\LogoutController;
use App\Domain\Account\Controllers\Auth\RegisterController;
use App\Domain\Account\Controllers\IntegrationSettingsController;
use App\Domain\Account\Controllers\MemberController;
use App\Domain\Account\Controllers\NotificationSettingsController;
use App\Domain\Account\Controllers\OnboardingController;
use App\Domain\Account\Controllers\OrganizationController;
use App\Domain\Account\Controllers\OrganizationSettingsController;
use App\Domain\Account\Controllers\ProfileController;
use App\Domain\Account\Controllers\ProfileSettingsController;
use App\Domain\Account\Controllers\ResellerController;
use App\Domain\Account\Controllers\SecuritySettingsController;
use App\Domain\Account\Controllers\SocialAuthController;
use App\Domain\ActionItem\Controllers\ActionItemBulkController;
use App\Domain\ActionItem\Controllers\ActionItemController;
use App\Domain\ActionItem\Controllers\ActionItemDashboardController;
use App\Domain\ActionItem\Controllers\ActionItemStatusController;
use App\Domain\AI\Controllers\ChatController;
use App\Domain\AI\Controllers\ExtractionController;
use App\Domain\AI\Controllers\ExtractionTemplateController;
use App\Domain\AI\Controllers\PrepBriefController;
use App\Domain\Analytics\Controllers\GovernanceAnalyticsController;
use App\Domain\Attendee\Controllers\AttendeeController;
use App\Domain\Attendee\Controllers\QrRegistrationController;
use App\Domain\Calendar\Controllers\CalendarConnectionController;
use App\Domain\Calendar\Controllers\CalendarWebhookController;
use App\Domain\Meeting\Controllers\BoardSettingController;
use App\Domain\Meeting\Controllers\DocumentController;
use App\Domain\Meeting\Controllers\ManualNoteController;
use App\Domain\Meeting\Controllers\MeetingController;
use App\Domain\Meeting\Controllers\OfflineDataController;
use App\Domain\Meeting\Controllers\ResolutionController;
use App\Domain\Meeting\Controllers\VoteController;
use App\Domain\Project\Controllers\ProjectController;
use App\Domain\Report\Controllers\GeneratedReportController;
use App\Domain\Report\Controllers\ReportTemplateController;
use App\Domain\Search\Controllers\SearchController;
use App\Domain\Transcription\Controllers\AudioChunkController;
use App\Domain\Transcription\Controllers\TranscriptionController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Root redirect
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Guest meeting view (no auth required)
Route::get('share/{token}', [\App\Domain\Collaboration\Controllers\GuestAccessController::class, 'show'])->name('guest.meeting');

// QR Registration (public)
Route::get('register/{token}', [QrRegistrationController::class, 'showForm'])->name('qr-registration.form');
Route::post('register/{token}', [QrRegistrationController::class, 'register'])->name('qr-registration.submit');
Route::get('register/{token}/success', [QrRegistrationController::class, 'success'])->name('qr-registration.success');

// Calendar Webhooks (no auth - called by Google/Microsoft)
Route::post('calendar/webhook/{provider}', [CalendarWebhookController::class, 'handle'])->name('calendar.webhook');

// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register']);

    Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [LogoutController::class, 'logout'])->name('logout');
});

Route::middleware(['auth'])->prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/step/{step}', [OnboardingController::class, 'show'])->name('step');
    Route::post('/step/{step}', [OnboardingController::class, 'update'])->name('update');
    Route::post('/skip', [OnboardingController::class, 'skip'])->name('skip');
});

Route::middleware(['auth', 'org.context', 'org.suspended', 'onboarding'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Global Search
    Route::get('search', [SearchController::class, 'index'])->name('search');
    Route::post('search/ai', \App\Domain\Search\Controllers\AiSearchController::class)->name('search.ai');

    // Profile
    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::put('profile/preferences', [ProfileController::class, 'updatePreferences'])->name('profile.preferences');
    Route::post('profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar');
    Route::get('profile/connected-accounts', [ProfileController::class, 'connectedAccounts'])->name('profile.connected-accounts');

    // Social Auth (unlink)
    Route::delete('auth/{provider}/unlink', [SocialAuthController::class, 'unlink'])->name('social.unlink');

    // Organizations
    Route::resource('organizations', OrganizationController::class);
    Route::resource('organizations.members', MemberController::class)->only(['index', 'store', 'update', 'destroy'])->shallow();
    Route::get('organizations/{organization}/settings', [OrganizationSettingsController::class, 'edit'])->name('organizations.settings.edit');
    Route::put('organizations/{organization}/settings', [OrganizationSettingsController::class, 'update'])->name('organizations.settings.update');
    Route::post('organizations/{organization}/settings/logo', [OrganizationSettingsController::class, 'uploadLogo'])->name('organizations.settings.logo');

    // Attendee Groups
    Route::resource('attendee-groups', \App\Domain\Attendee\Controllers\AttendeeGroupController::class)->except(['show']);

    // Meeting Templates
    Route::resource('meeting-templates', \App\Domain\Meeting\Controllers\MeetingTemplateController::class);

    // Projects
    Route::resource('projects', ProjectController::class);
    Route::post('projects/{project}/members', [ProjectController::class, 'addMember'])->name('projects.members.add');
    Route::delete('projects/{project}/members/{user}', [ProjectController::class, 'removeMember'])->name('projects.members.remove');

    // Meeting Series
    Route::resource('meeting-series', \App\Domain\Meeting\Controllers\MeetingSeriesController::class);
    Route::post('meeting-series/{meetingSeries}/generate', [\App\Domain\Meeting\Controllers\MeetingSeriesController::class, 'generateMeetings'])->name('meeting-series.generate');

    // Tags
    Route::get('tags', [\App\Domain\Meeting\Controllers\MomTagController::class, 'index'])->name('tags.index');
    Route::post('tags', [\App\Domain\Meeting\Controllers\MomTagController::class, 'store'])->name('tags.store');
    Route::put('tags/{momTag}', [\App\Domain\Meeting\Controllers\MomTagController::class, 'update'])->name('tags.update');
    Route::delete('tags/{momTag}', [\App\Domain\Meeting\Controllers\MomTagController::class, 'destroy'])->name('tags.destroy');

    // Meetings
    Route::get('meetings/calendar-data', [MeetingController::class, 'calendarData'])->name('meetings.calendar-data');
    Route::resource('meetings', MeetingController::class);
    Route::post('meetings/{meeting}/finalize', [MeetingController::class, 'finalize'])->name('meetings.finalize');
    Route::post('meetings/{meeting}/approve', [MeetingController::class, 'approve'])->name('meetings.approve');
    Route::post('meetings/{meeting}/revert', [MeetingController::class, 'revert'])->name('meetings.revert');
    Route::post('meetings/{meeting}/duplicate', [MeetingController::class, 'duplicate'])->name('meetings.duplicate');
    Route::get('meetings/{meeting}/versions', [\App\Domain\Meeting\Controllers\MomVersionController::class, 'index'])->name('meetings.versions.index');
    Route::get('meetings/{meeting}/versions/{version}', [\App\Domain\Meeting\Controllers\MomVersionController::class, 'show'])->name('meetings.versions.show');

    // Analytics
    Route::get('analytics', [\App\Domain\Analytics\Controllers\AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('analytics/data', [\App\Domain\Analytics\Controllers\AnalyticsController::class, 'data'])->name('analytics.data');
    Route::get('analytics/governance', [GovernanceAnalyticsController::class, 'index'])->name('analytics.governance');
    Route::get('analytics/governance/data', [GovernanceAnalyticsController::class, 'data'])->name('analytics.governance.data');
    Route::get('analytics/governance/export', [GovernanceAnalyticsController::class, 'export'])->name('analytics.governance.export');

    // Audit Log
    Route::get('audit-log', [\App\Domain\Account\Controllers\AuditLogController::class, 'index'])->name('audit-log.index');

    // API Keys
    Route::get('api-keys', [\App\Domain\Account\Controllers\ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('api-keys', [\App\Domain\Account\Controllers\ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('api-keys/{apiKey}', [\App\Domain\Account\Controllers\ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

    // Usage Tracking
    Route::get('subscription', [\App\Domain\Account\Controllers\SubscriptionController::class, 'index'])->name('subscription.index');
    Route::get('usage', [\App\Domain\Account\Controllers\UsageController::class, 'index'])->name('usage.index');

    // Notifications
    Route::get('notifications', [\App\Domain\Account\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread', [\App\Domain\Account\Controllers\NotificationController::class, 'unread'])->name('notifications.unread');
    Route::post('notifications/read-all', [\App\Domain\Account\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read', [\App\Domain\Account\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');

    // Calendar Connections
    Route::prefix('calendar')->name('calendar.')->group(function () {
        Route::get('connections', [CalendarConnectionController::class, 'index'])->name('connections');
        Route::get('connect/{provider}', [CalendarConnectionController::class, 'connect'])->name('connect');
        Route::get('callback/{provider}', [CalendarConnectionController::class, 'callback'])->name('callback');
        Route::delete('disconnect/{connection}', [CalendarConnectionController::class, 'disconnect'])->name('disconnect');
    });

    // AI Provider Configs
    Route::resource('ai-provider-configs', \App\Domain\Account\Controllers\AiProviderConfigController::class);

    // Profile Settings
    Route::get('settings/profile', [ProfileSettingsController::class, 'edit'])->name('settings.profile');
    Route::put('settings/profile', [ProfileSettingsController::class, 'update'])->name('settings.profile.update');

    // Notification Settings
    Route::get('settings/notifications', [NotificationSettingsController::class, 'edit'])->name('settings.notifications');
    Route::put('settings/notifications', [NotificationSettingsController::class, 'update'])->name('settings.notifications.update');

    // Security Settings
    Route::get('settings/security', [SecuritySettingsController::class, 'edit'])->name('settings.security');
    Route::put('settings/security/password', [SecuritySettingsController::class, 'updatePassword'])->name('settings.security.password');

    // Integration Settings
    Route::get('settings/integrations', [IntegrationSettingsController::class, 'index'])->name('settings.integrations');

    // API Key Settings
    Route::get('settings/api-keys', [ApiKeySettingsController::class, 'index'])->name('settings.api-keys');
    Route::post('settings/api-keys', [ApiKeySettingsController::class, 'store'])->name('settings.api-keys.store');
    Route::delete('settings/api-keys/{apiKey}', [ApiKeySettingsController::class, 'destroy'])->name('settings.api-keys.destroy');

    // Board Settings
    Route::get('settings/board', [BoardSettingController::class, 'edit'])->name('settings.board.edit');
    Route::put('settings/board', [BoardSettingController::class, 'update'])->name('settings.board.update');

    // Export Templates
    Route::resource('settings/export-templates', \App\Domain\Export\Controllers\ExportTemplateController::class)
        ->names('settings.export-templates')
        ->except(['show'])
        ->parameters(['export-templates' => 'exportTemplate']);

    // Extraction Templates
    Route::resource('extraction-templates', ExtractionTemplateController::class)->except(['show']);

    // Cross-meeting dashboards
    Route::get('action-items', [ActionItemDashboardController::class, 'index'])->name('action-items.dashboard');
    Route::post('action-items/bulk', ActionItemBulkController::class)->name('action-items.bulk');

    // QR Registration token generation & management
    Route::post('meetings/{meeting}/qr-registration', [QrRegistrationController::class, 'generate'])
        ->name('meetings.qr-registration.generate');
    Route::post('meetings/{meeting}/qr-registration/disable', [QrRegistrationController::class, 'disable'])
        ->name('meetings.qr-registration.disable');

    // Meeting sub-resources (transcriptions, notes, attendees, actions, chat, extractions)
    Route::prefix('meetings/{meeting}')->as('meetings.')->group(function () {
        Route::resource('transcriptions', TranscriptionController::class)->only(['store', 'show', 'destroy']);
        Route::patch('transcriptions/{transcription}/speakers', [\App\Domain\Transcription\Controllers\SpeakerController::class, 'update'])->name('transcriptions.speakers.update');
        Route::resource('documents', DocumentController::class)->only(['store', 'destroy']);
        Route::resource('manual-notes', ManualNoteController::class);
        Route::post('extract', [ExtractionController::class, 'extract'])->name('extract');
        Route::post('generate', [ExtractionController::class, 'generate'])->name('generate');
        Route::get('extractions', [ExtractionController::class, 'index'])->name('extractions.index');
        Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
        Route::post('chat', [ChatController::class, 'store'])->name('chat.store');
        Route::post('action-items/create-all-tasks', [ActionItemController::class, 'createAllTasks'])->name('action-items.create-all-tasks');
        Route::resource('action-items', ActionItemController::class);
        Route::post('action-items/{actionItem}/carry-forward', [ActionItemController::class, 'carryForward'])->name('action-items.carry-forward');
        Route::patch('action-items/{actionItem}/status', [ActionItemStatusController::class, 'update'])->name('action-items.status');

        Route::resource('attendees', AttendeeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('attendees/bulk-invite', [AttendeeController::class, 'bulkInvite'])->name('attendees.bulk-invite');
        Route::patch('attendees/{attendee}/rsvp', [AttendeeController::class, 'updateRsvp'])->name('attendees.rsvp');
        Route::patch('attendees/{attendee}/presence', [AttendeeController::class, 'markPresence'])->name('attendees.presence');

        Route::get('export/pdf', [\App\Domain\Export\Controllers\ExportController::class, 'pdf'])->name('export.pdf');
        Route::get('export/word', [\App\Domain\Export\Controllers\ExportController::class, 'word'])->name('export.word');
        Route::get('export/csv', [\App\Domain\Export\Controllers\ExportController::class, 'csv'])->name('export.csv');
        Route::post('email-distribution', [\App\Domain\Export\Controllers\EmailDistributionController::class, 'store'])->name('email-distribution.store');

        Route::get('shares', [\App\Domain\Collaboration\Controllers\ShareController::class, 'index'])->name('shares.index');
        Route::post('shares', [\App\Domain\Collaboration\Controllers\ShareController::class, 'store'])->name('shares.store');
        Route::delete('shares/{share}', [\App\Domain\Collaboration\Controllers\ShareController::class, 'destroy'])->name('shares.destroy');

        // Audio chunks (browser recording)
        Route::post('audio-chunks', [AudioChunkController::class, 'store'])->name('audio-chunks.store');
        Route::post('audio-chunks/finalize', [AudioChunkController::class, 'finalize'])->name('audio-chunks.finalize');
        Route::delete('audio-chunks', [AudioChunkController::class, 'destroy'])->name('audio-chunks.destroy');

        // Comments
        Route::post('comments', [\App\Domain\Collaboration\Controllers\CommentController::class, 'store'])->name('comments.store');

        // Follow-up Email
        Route::get('follow-up-email', [\App\Domain\AI\Controllers\FollowUpEmailController::class, 'generate'])->name('follow-up-email.generate');
        Route::post('follow-up-email', [\App\Domain\AI\Controllers\FollowUpEmailController::class, 'send'])->name('follow-up-email.send');

        // Meeting Preparation
        Route::get('prepare-agenda', [\App\Domain\AI\Controllers\MeetingPreparationController::class, 'generate'])->name('prepare-agenda.generate');
        Route::post('prepare-agenda', [\App\Domain\AI\Controllers\MeetingPreparationController::class, 'apply'])->name('prepare-agenda.apply');

        // Prep Brief
        Route::get('prep-brief', [PrepBriefController::class, 'show'])->name('prep-brief');
        Route::post('prep-brief/generate', [PrepBriefController::class, 'generate'])->name('prep-brief.generate');
        Route::post('prep-brief/{brief}/section-read', [PrepBriefController::class, 'markSectionRead'])->name('prep-brief.section-read');

        // Live Meeting
        Route::post('live/start', [\App\Domain\LiveMeeting\Controllers\LiveMeetingController::class, 'start'])->name('live.start');
        Route::get('live/{session}', [\App\Domain\LiveMeeting\Controllers\LiveMeetingController::class, 'show'])->name('live.show');
        Route::post('live/{session}/chunk', [\App\Domain\LiveMeeting\Controllers\LiveMeetingController::class, 'chunk'])->name('live.chunk');
        Route::post('live/{session}/end', [\App\Domain\LiveMeeting\Controllers\LiveMeetingController::class, 'end'])->name('live.end');
        Route::get('live/{session}/state', [\App\Domain\LiveMeeting\Controllers\LiveMeetingController::class, 'state'])->name('live.state');

        // Offline Data
        Route::get('offline-data', [OfflineDataController::class, 'show'])->name('offline-data');

        // Resolutions & Voting (Board Compliance)
        Route::post('resolutions', [ResolutionController::class, 'store'])->name('resolutions.store');
        Route::put('resolutions/{resolution}', [ResolutionController::class, 'update'])->name('resolutions.update');
        Route::delete('resolutions/{resolution}', [ResolutionController::class, 'destroy'])->name('resolutions.destroy');
        Route::post('resolutions/{resolution}/vote', [VoteController::class, 'store'])->name('resolutions.vote');
    });

    Route::put('comments/{comment}', [\App\Domain\Collaboration\Controllers\CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [\App\Domain\Collaboration\Controllers\CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('comments/{comment}/reactions', [\App\Domain\Collaboration\Controllers\ReactionController::class, 'toggle'])->name('comments.reactions.toggle');

    // Reports
    Route::get('reports/generated', [GeneratedReportController::class, 'index'])->name('reports.generated.index');
    Route::get('reports/generated/{report}/download', [GeneratedReportController::class, 'download'])->name('reports.generated.download');
    Route::resource('reports', ReportTemplateController::class);
    Route::post('reports/{report}/generate', [ReportTemplateController::class, 'generate'])->name('reports.generate');

    // Webhooks
    Route::resource('webhooks', \App\Domain\Webhook\Controllers\WebhookEndpointController::class);
    Route::post('webhooks/{webhook}/ping', [\App\Domain\Webhook\Controllers\WebhookEndpointController::class, 'ping'])->name('webhooks.ping');

    // Offline Sync
    Route::post('offline/sync', [OfflineDataController::class, 'sync'])->name('offline.sync');

    // Reseller
    Route::prefix('reseller')->name('reseller.')->group(function () {
        Route::get('/', [ResellerController::class, 'dashboard'])->name('dashboard');
        Route::get('sub-organizations', [ResellerController::class, 'subOrganizations'])->name('sub-organizations');
        Route::get('sub-organizations/create', [ResellerController::class, 'createSubOrg'])->name('sub-organizations.create');
        Route::post('sub-organizations', [ResellerController::class, 'storeSubOrg'])->name('sub-organizations.store');
    });
});
