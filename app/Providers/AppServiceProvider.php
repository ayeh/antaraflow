<?php

namespace App\Providers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Policies\OrganizationPolicy;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Policies\ActionItemPolicy;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Policies\MinutesOfMeetingPolicy;
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
        Gate::policy(ActionItem::class, ActionItemPolicy::class);

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
