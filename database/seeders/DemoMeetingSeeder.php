<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Meeting\Models\MeetingSeries;
use App\Domain\Meeting\Models\MeetingTemplate;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomTag;
use App\Support\Enums\ActionItemPriority;
use App\Support\Enums\ActionItemStatus;
use Illuminate\Database\Seeder;

class DemoMeetingSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::query()->where('slug', 'antaraflow-demo')->firstOrFail();
        $users = $org->members()->get();
        $owner = $users->first();

        $tags = collect(['engineering', 'product', 'standup', 'review'])->map(
            fn (string $name) => MomTag::factory()->create([
                'organization_id' => $org->id,
                'name' => $name,
                'slug' => $name,
            ])
        );

        $series = MeetingSeries::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Weekly Engineering Standup',
            'recurrence_pattern' => 'weekly',
        ]);

        $template = MeetingTemplate::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $owner->id,
            'name' => 'Sprint Retrospective Template',
        ]);

        $draftMeetings = MinutesOfMeeting::factory()->count(3)->draft()->create([
            'organization_id' => $org->id,
            'created_by' => $owner->id,
        ]);

        $finalizedMeetings = MinutesOfMeeting::factory()->count(2)->finalized()->create([
            'organization_id' => $org->id,
            'created_by' => $owner->id,
        ]);

        $approvedMeeting = MinutesOfMeeting::factory()->approved()->create([
            'organization_id' => $org->id,
            'created_by' => $owner->id,
        ]);

        $allMeetings = $draftMeetings->merge($finalizedMeetings)->push($approvedMeeting);

        foreach ($allMeetings as $meeting) {
            $attendeeCount = fake()->numberBetween(2, 4);
            $attendeeUsers = $users->random(min($attendeeCount, $users->count()));

            foreach ($attendeeUsers as $attendeeUser) {
                MomAttendee::factory()->create([
                    'minutes_of_meeting_id' => $meeting->id,
                    'user_id' => $attendeeUser->id,
                    'name' => $attendeeUser->name,
                    'email' => $attendeeUser->email,
                ]);
            }
        }

        foreach ($finalizedMeetings as $meeting) {
            $meeting->tags()->attach($tags->random(2)->pluck('id'));

            ActionItem::factory()->create([
                'organization_id' => $org->id,
                'minutes_of_meeting_id' => $meeting->id,
                'assigned_to' => $users->random()->id,
                'created_by' => $owner->id,
                'status' => ActionItemStatus::Open,
                'priority' => ActionItemPriority::High,
            ]);

            ActionItem::factory()->completed()->create([
                'organization_id' => $org->id,
                'minutes_of_meeting_id' => $meeting->id,
                'assigned_to' => $users->random()->id,
                'created_by' => $owner->id,
            ]);

            ActionItem::factory()->overdue()->create([
                'organization_id' => $org->id,
                'minutes_of_meeting_id' => $meeting->id,
                'assigned_to' => $users->random()->id,
                'created_by' => $owner->id,
            ]);
        }

        $approvedMeeting->tags()->attach($tags->where('name', 'review')->first()->id);

        ActionItem::factory()->create([
            'organization_id' => $org->id,
            'minutes_of_meeting_id' => $approvedMeeting->id,
            'assigned_to' => $users->random()->id,
            'created_by' => $owner->id,
            'status' => ActionItemStatus::InProgress,
        ]);

        $seriesMeeting = $draftMeetings->first();
        $seriesMeeting->update(['meeting_series_id' => $series->id]);

        $templateMeeting = $draftMeetings->last();
        $templateMeeting->update(['meeting_template_id' => $template->id]);
    }
}
