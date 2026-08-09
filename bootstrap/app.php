<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/admin.php'));

            // SubstituteBindings is not implicit here: routes registered
            // through `then` get no middleware group, so without it every
            // route-model parameter arrives as an empty model.
            Route::prefix('api/mobile/v1')
                ->middleware([\Illuminate\Routing\Middleware\SubstituteBindings::class])
                ->name('mobile.')
                ->group(base_path('routes/mobile.php'));
        },
    )
    ->withCommands([
        __DIR__.'/../app/Domain',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\ResolveSubdomain::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\SetLocale::class,
        ]);

        $middleware->alias([
            'org.context' => \App\Infrastructure\Tenancy\SetOrganizationContext::class,
            'org.suspended' => \App\Domain\Admin\Middleware\CheckOrganizationSuspended::class,
            'admin.auth' => \App\Domain\Admin\Middleware\AdminAuthenticated::class,
            'onboarding' => \App\Domain\Account\Middleware\OnboardingMiddleware::class,
            'mobile.org' => \App\Domain\API\Middleware\Mobile\ResolveMobileOrganization::class,
            'mobile.version' => \App\Domain\API\Middleware\Mobile\EnsureClientVersion::class,
            'mobile.idempotency' => \App\Domain\API\Middleware\Mobile\MobileIdempotency::class,
        ]);

        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\ThrottleRequests::class,
            prepend: \App\Domain\API\Middleware\ApiKeyAuthentication::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        \App\Domain\API\Exceptions\MobileExceptionRenderer::register($exceptions);
    })->create();
