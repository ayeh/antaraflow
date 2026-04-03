<?php

declare(strict_types=1);

namespace App\Domain\Export\Controllers;

use App\Domain\Export\Jobs\SendMomEmailJob;
use App\Domain\Export\Models\MomEmailDistribution;
use App\Domain\Export\Requests\SendEmailDistributionRequest;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Http\RedirectResponse;

class EmailDistributionController
{
    public function store(SendEmailDistributionRequest $request, MinutesOfMeeting $meeting): RedirectResponse
    {
        abort_unless(
            $meeting->organization_id === $request->user()->current_organization_id,
            403
        );

        $dist = MomEmailDistribution::create([
            'minutes_of_meeting_id' => $meeting->id,
            'sent_by' => $request->user()->id,
            'recipients' => $request->validated('recipients'),
            'subject' => $request->validated('subject'),
            'body_note' => $request->validated('body_note'),
            'export_format' => $request->validated('export_format'),
            'status' => 'pending',
        ]);

        SendMomEmailJob::dispatch($dist);

        return redirect()->route('meetings.show', $meeting)->with('success', 'Email queued for delivery.');
    }
}
