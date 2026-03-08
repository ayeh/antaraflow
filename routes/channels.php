<?php

use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('meeting.{meetingId}', function (User $user, int $meetingId) {
    $meeting = MinutesOfMeeting::find($meetingId);

    return $meeting && $meeting->organization_id === $user->current_organization_id;
});

Broadcast::channel('meeting.{meetingId}.presence', function (User $user, int $meetingId) {
    $meeting = MinutesOfMeeting::find($meetingId);

    if ($meeting && $meeting->organization_id === $user->current_organization_id) {
        return ['id' => $user->id, 'name' => $user->name];
    }

    return false;
});

Broadcast::channel('organization.{orgId}', function (User $user, int $orgId) {
    return $user->current_organization_id === $orgId;
});

Broadcast::channel('live-meeting.{sessionId}', function (User $user, int $sessionId) {
    $session = \App\Domain\LiveMeeting\Models\LiveMeetingSession::find($sessionId);

    return $session && $session->meeting->organization_id === $user->current_organization_id;
});
