<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        if (! in_array($locale, SetLocale::SUPPORTED_LOCALES, true)) {
            $locale = config('app.locale');
        }

        $request->session()->put('locale', $locale);

        return back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
    }
}
