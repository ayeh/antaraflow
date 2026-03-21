<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Export\Jobs\SendMomEmailJob;
use App\Domain\Export\Models\MomEmailDistribution;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user->id, ['role' => 'member']);
    $this->meeting = MinutesOfMeeting::factory()->for($this->org)->create();
});

it('queues email distribution job', function (): void {
    Queue::fake();

    $this->actingAs($this->user)
        ->post(route('meetings.email-distribution.store', $this->meeting), [
            'recipients' => ['test@example.com', 'another@example.com'],
            'subject' => 'Meeting Minutes',
            'export_format' => 'pdf',
        ])
        ->assertRedirect();

    Queue::assertPushed(SendMomEmailJob::class);
    expect(MomEmailDistribution::count())->toBe(1);
});
