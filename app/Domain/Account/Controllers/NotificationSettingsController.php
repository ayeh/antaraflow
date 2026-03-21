<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\UserSettings;
use App\Domain\Account\Requests\UpdateNotificationSettingsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $settings = UserSettings::firstOrNew(['user_id' => $request->user()->id]);
        $prefs = $settings->notification_preferences ?? $this->defaultPreferences();

        return view('settings.notifications', ['prefs' => $prefs]);
    }

    public function update(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        $eventTypes = ['mention_in_comment', 'action_item_assigned', 'meeting_finalized', 'action_item_overdue'];
        $prefs = [];

        foreach ($eventTypes as $type) {
            $prefs[$type] = [
                'email' => (bool) $request->input("{$type}.email", false),
                'in_app' => (bool) $request->input("{$type}.in_app", false),
            ];
        }

        UserSettings::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['notification_preferences' => $prefs]
        );

        return redirect()->route('settings.notifications')->with('success', 'Notification preferences saved.');
    }

    /** @return array<string, array<string, bool>> */
    private function defaultPreferences(): array
    {
        return [
            'mention_in_comment' => ['email' => true, 'in_app' => true],
            'action_item_assigned' => ['email' => true, 'in_app' => true],
            'meeting_finalized' => ['email' => false, 'in_app' => true],
            'action_item_overdue' => ['email' => true, 'in_app' => true],
        ];
    }
}
