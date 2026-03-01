<?php

namespace App\Providers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Policies\OrganizationPolicy;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Policies\ActionItemPolicy;
use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MeetingShare;
use App\Domain\Collaboration\Policies\CommentPolicy;
use App\Domain\Collaboration\Policies\MeetingSharePolicy;
use App\Domain\Meeting\Models\MeetingSeries;
use App\Domain\Meeting\Models\MeetingTemplate;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomTag;
use App\Domain\Meeting\Policies\MeetingSeriesPolicy;
use App\Domain\Meeting\Policies\MeetingTemplatePolicy;
use App\Domain\Meeting\Policies\MinutesOfMeetingPolicy;
use App\Domain\Meeting\Policies\MomTagPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(MinutesOfMeeting::class, MinutesOfMeetingPolicy::class);
        Gate::policy(MeetingTemplate::class, MeetingTemplatePolicy::class);
        Gate::policy(MomTag::class, MomTagPolicy::class);
        Gate::policy(MeetingSeries::class, MeetingSeriesPolicy::class);
        Gate::policy(ActionItem::class, ActionItemPolicy::class);
        Gate::policy(MeetingShare::class, MeetingSharePolicy::class);
        Gate::policy(Comment::class, CommentPolicy::class);

        View::composer('layouts.app', function ($view) {
            if (Auth::check() && Auth::user()->current_organization_id) {
                $view->with('recentMeetings', MinutesOfMeeting::query()
                    ->where('organization_id', Auth::user()->current_organization_id)
                    ->latest()
                    ->limit(5)
                    ->get(['id', 'title', 'meeting_date']));
            }
        });
    }
}
