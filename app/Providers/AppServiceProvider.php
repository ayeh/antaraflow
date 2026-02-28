<?php

namespace App\Providers;

use App\Domain\Account\Models\Organization;
use App\Domain\Account\Policies\OrganizationPolicy;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\ActionItem\Policies\ActionItemPolicy;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Policies\MinutesOfMeetingPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(MinutesOfMeeting::class, MinutesOfMeetingPolicy::class);
        Gate::policy(ActionItem::class, ActionItemPolicy::class);
    }
}
