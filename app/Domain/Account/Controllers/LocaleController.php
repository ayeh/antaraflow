<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\UserSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocaleController extends Controller
{
    /**
     * Switch the active language for the current session, persisting the choice
     * to the authenticated user's settings when signed in.
     */
    public function update(Request $request, string $locale): RedirectResponse
    {
        $supported = array_keys(config('locales.supported', ['en' => 'English']));

        abort_unless(in_array($locale, $supported, true), 404);

        $request->session()->put('locale', $locale);

        if ($request->user()) {
            UserSettings::updateOrCreate(
                ['user_id' => $request->user()->id],
                ['locale' => $locale],
            );
        }

        return redirect()->back();
    }
}
