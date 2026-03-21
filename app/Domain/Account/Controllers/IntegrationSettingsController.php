<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Calendar\Models\CalendarConnection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class IntegrationSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $calendarConnections = CalendarConnection::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $googleConnected = $calendarConnections->where('provider', 'google')->isNotEmpty();
        $microsoftConnected = $calendarConnections->where('provider', 'microsoft')->isNotEmpty();

        $org = $user->currentOrganization;
        $teamsWebhookConfigured = $org?->hasTeamsWebhook() ?? false;

        return view('settings.integrations', compact('googleConnected', 'microsoftConnected', 'teamsWebhookConfigured', 'org'));
    }
}
