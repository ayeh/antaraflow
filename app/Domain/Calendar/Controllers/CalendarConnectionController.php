<?php

declare(strict_types=1);

namespace App\Domain\Calendar\Controllers;

use App\Domain\Calendar\Models\CalendarConnection;
use App\Domain\Calendar\Services\CalendarSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CalendarConnectionController extends Controller
{
    public function __construct(
        private readonly CalendarSyncService $calendarSyncService,
    ) {}

    public function index(Request $request): View
    {
        $connections = CalendarConnection::query()
            ->where('user_id', $request->user()->id)
            ->where('organization_id', $request->user()->current_organization_id)
            ->get();

        return view('calendar.connections', compact('connections'));
    }

    public function connect(Request $request, string $provider): RedirectResponse
    {
        if (! in_array($provider, ['google', 'outlook'])) {
            abort(404);
        }

        $state = Str::random(40);
        session(['calendar_oauth_state' => $state]);

        $redirectUri = route('calendar.callback', $provider);
        $calendarProvider = $this->calendarSyncService->resolveProvider($provider);
        $authUrl = $calendarProvider->getAuthUrl($redirectUri, $state);

        return redirect()->away($authUrl);
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        if ($request->get('state') !== session('calendar_oauth_state')) {
            return redirect()->route('calendar.connections')
                ->with('error', 'Invalid OAuth state. Please try again.');
        }

        session()->forget('calendar_oauth_state');

        $calendarProvider = $this->calendarSyncService->resolveProvider($provider);
        $redirectUri = route('calendar.callback', $provider);
        $tokens = $calendarProvider->handleCallback($request->get('code'), $redirectUri);

        CalendarConnection::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'provider' => $provider,
            ],
            [
                'organization_id' => $request->user()->current_organization_id,
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? null,
                'token_expires_at' => $tokens['expires_at'] ?? null,
                'is_active' => true,
            ]
        );

        return redirect()->route('calendar.connections')
            ->with('success', ucfirst($provider).' Calendar connected successfully.');
    }

    public function disconnect(Request $request, CalendarConnection $connection): RedirectResponse
    {
        if ($connection->user_id !== $request->user()->id) {
            abort(403);
        }

        $connection->delete();

        return redirect()->route('calendar.connections')
            ->with('success', 'Calendar disconnected.');
    }

    public function toggleAutoRecord(Request $request, CalendarConnection $connection): RedirectResponse
    {
        if ($connection->user_id !== $request->user()->id) {
            abort(403);
        }

        $connection->update([
            'auto_record' => ! $connection->auto_record,
        ]);

        $status = $connection->auto_record ? 'enabled' : 'disabled';

        return redirect()->route('calendar.connections')
            ->with('success', "Auto-record {$status} for ".ucfirst($connection->provider).'.');
    }
}
