<?php

declare(strict_types=1);

namespace App\Domain\Account\Controllers;

use App\Domain\Account\Models\UserSettings;
use App\Domain\Account\Requests\UpdateNotificationSettingsRequest;
use App\Domain\Account\Support\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    /** What this form calls each kind, against the canonical key. */
    private const VIEW_KEYS = [
        'mention_in_comment' => 'mention',
        'action_item_assigned' => 'action_item_assigned',
        'meeting_finalized' => 'meeting_finalized',
        'action_item_overdue' => 'action_item_overdue',
    ];

    public function edit(Request $request): View
    {
        $resolved = NotificationPreferences::for($request->user());

        // The view still speaks the old dialect — `mention_in_comment`, and
        // `in_app` for what is really the push switch. Translated here rather
        // than keeping two spellings alive in storage.
        $prefs = [];

        foreach (self::VIEW_KEYS as $display => $canonical) {
            $prefs[$display] = [
                'email' => $resolved[$canonical]['email'],
                'in_app' => $resolved[$canonical]['push'],
            ];
        }

        return view('settings.notifications', ['prefs' => $prefs]);
    }

    public function update(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        $changes = [];

        foreach (array_keys(self::VIEW_KEYS) as $type) {
            $changes[$type] = [
                'email' => (bool) $request->input("{$type}.email", false),
                'in_app' => (bool) $request->input("{$type}.in_app", false),
            ];
        }

        // Merged rather than assembled from scratch: this form covers four of
        // the eleven kinds, and rebuilding the whole document from it dropped
        // the other seven along with every push preference set on a phone.
        $prefs = NotificationPreferences::merge($request->user(), $changes);

        UserSettings::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['notification_preferences' => $prefs]
        );

        return redirect()->route('settings.notifications')->with('success', __('Notification preferences saved.'));
    }
}
