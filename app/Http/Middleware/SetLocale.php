<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Account\Models\UserSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Resolve the active locale for the request from (in order of precedence):
     * an explicit session choice, the authenticated user's saved preference,
     * then the app default. The session is only written by an explicit switch
     * (LocaleController / profile settings), never here, so a saved account
     * preference is not shadowed by an auto-applied default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolveLocale($request));

        return $next($request);
    }

    protected function resolveLocale(Request $request): string
    {
        $supported = array_keys(config('locales.supported', ['en' => 'English']));

        $sessionLocale = $request->session()->get('locale');
        if (is_string($sessionLocale) && in_array($sessionLocale, $supported, true)) {
            return $sessionLocale;
        }

        if ($request->user()) {
            $saved = UserSettings::query()
                ->where('user_id', $request->user()->id)
                ->value('locale');

            if (is_string($saved) && in_array($saved, $supported, true)) {
                return $saved;
            }
        }

        return config('app.locale', 'en');
    }
}
