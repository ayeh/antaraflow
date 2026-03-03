<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Admin\Services\BrandingService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class BrandingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BrandingService::class);
    }

    public function boot(): void
    {
        View::composer('*', function ($view) {
            if (! $view->offsetExists('branding')) {
                $view->with('branding', app(BrandingService::class));
            }
        });
    }
}
