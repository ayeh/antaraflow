<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Policies\OrganizationPolicy;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Policies\ActionItemPolicy;
use App\Domain\AI\Events\ExtractionCompleted;
use App\Domain\AI\Events\ExtractionFailed;
use App\Domain\AI\Listeners\NotifyExtractionComplete;
use App\Domain\AI\Listeners\NotifyExtractionFailed;
use App\Domain\AI\Models\ExtractionTemplate;
use App\Domain\AI\Policies\ExtractionTemplatePolicy;
use App\Domain\Attendee\Models\AttendeeGroup;
use App\Domain\Attendee\Policies\AttendeeGroupPolicy;
use App\Domain\Calendar\Listeners\SyncMeetingToCalendar;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MeetingShare;
use App\Domain\Collaboration\Policies\CommentPolicy;
use App\Domain\Collaboration\Policies\MeetingSharePolicy;
use App\Domain\Meeting\Events\MeetingApproved;
use App\Domain\Meeting\Events\MeetingFinalized;
use App\Domain\Meeting\Listeners\NotifyMeetingApproved;
use App\Domain\Meeting\Listeners\NotifyMeetingFinalized;
use App\Domain\Meeting\Models\MeetingResolution;
use App\Domain\Meeting\Models\MeetingSeries;
use App\Domain\Meeting\Models\MeetingTemplate;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomTag;
use App\Domain\Meeting\Policies\MeetingSeriesPolicy;
use App\Domain\Meeting\Policies\MeetingTemplatePolicy;
use App\Domain\Meeting\Policies\MinutesOfMeetingPolicy;
use App\Domain\Meeting\Policies\MomTagPolicy;
use App\Domain\Meeting\Policies\ResolutionPolicy;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Policies\ProjectPolicy;
use App\Domain\Report\Models\ReportTemplate;
use App\Domain\Report\Policies\ReportTemplatePolicy;
use App\Domain\Transcription\Events\TranscriptionCompleted;
use App\Domain\Transcription\Events\TranscriptionFailed;
use App\Domain\Transcription\Listeners\NotifyTranscriptionComplete;
use App\Domain\Transcription\Listeners\NotifyTranscriptionFailed;
use App\Domain\Webhook\Listeners\WebhookEventSubscriber;
use App\Domain\Webhook\Models\WebhookEndpoint;
use App\Domain\Webhook\Policies\WebhookEndpointPolicy;
use App\Infrastructure\Notifications\Channels\TeamsChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            $apiKey = $request->attributes->get('api_key');

            if ($apiKey) {
                $orgId = $apiKey->organization_id;
                // Check if org has an active paid plan (pro/enterprise tier gets higher rate limit)
                $activeSubscription = \App\Domain\Account\Models\OrganizationSubscription::query()
                    ->where('organization_id', $orgId)
                    ->whereNotNull('starts_at')
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                    ->with('subscriptionPlan')
                    ->first();
                $planSlug = $activeSubscription?->subscriptionPlan?->slug ?? '';
                // Pro and enterprise plans get 300 req/min; free/starter get 60 req/min
                $limit = str_contains($planSlug, 'pro') || str_contains($planSlug, 'enterprise') ? 300 : 60;

                return Limit::perMinute($limit)->by($apiKey->id);
            }

            return Limit::perMinute(60)->by($request->ip());
        });

        Gate::policy(AttendeeGroup::class, AttendeeGroupPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(MinutesOfMeeting::class, MinutesOfMeetingPolicy::class);
        Gate::policy(MeetingTemplate::class, MeetingTemplatePolicy::class);
        Gate::policy(MomTag::class, MomTagPolicy::class);
        Gate::policy(MeetingSeries::class, MeetingSeriesPolicy::class);
        Gate::policy(ActionItem::class, ActionItemPolicy::class);
        Gate::policy(MeetingShare::class, MeetingSharePolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(ExtractionTemplate::class, ExtractionTemplatePolicy::class);
        Gate::policy(MeetingResolution::class, ResolutionPolicy::class);
        Gate::policy(ReportTemplate::class, ReportTemplatePolicy::class);
        Gate::policy(WebhookEndpoint::class, WebhookEndpointPolicy::class);

        View::composer('layouts.app', function ($view) {
            if (Auth::check() && Auth::user()->current_organization_id) {
                $view->with('recentMeetings', MinutesOfMeeting::query()
                    ->where('organization_id', Auth::user()->current_organization_id)
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'title', 'meeting_date']));
            }
        });

        Event::listen(TranscriptionCompleted::class, NotifyTranscriptionComplete::class);
        Event::listen(TranscriptionFailed::class, NotifyTranscriptionFailed::class);
        Event::listen(ExtractionCompleted::class, NotifyExtractionComplete::class);
        Event::listen(ExtractionFailed::class, NotifyExtractionFailed::class);
        Event::listen(MeetingFinalized::class, NotifyMeetingFinalized::class);
        Event::listen(MeetingFinalized::class, SyncMeetingToCalendar::class);
        Event::listen(MeetingApproved::class, NotifyMeetingApproved::class);

        Event::subscribe(WebhookEventSubscriber::class);

        Notification::extend('teams', function ($app) {
            return $app->make(TeamsChannel::class);
        });
    }
}
