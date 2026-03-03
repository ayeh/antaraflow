<?php

declare(strict_types=1);

namespace App\Domain\Admin\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('admin')->check()) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
