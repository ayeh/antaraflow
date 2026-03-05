<?php

declare(strict_types=1);

namespace App\Domain\Account\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OnboardingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()
            && $request->user()->onboarding_completed_at === null
            && ! $request->routeIs('onboarding.*', 'login', 'logout', 'register', 'api.*')
        ) {
            return redirect()->route('onboarding.step', ['step' => 1]);
        }

        return $next($request);
    }
}
