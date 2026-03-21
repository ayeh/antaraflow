<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Collaboration\Models\MomMention;
use App\Domain\Collaboration\Services\CommentService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates mention records for @username in comment body', function (): void {
    $org = Organization::factory()->create();
    $author = User::factory()->create(['name' => 'Author User']);
    $mentioned = User::factory()->create(['name' => 'Ahmad Zaki']);

    $org->members()->attach([
        $author->id => ['role' => 'member'],
        $mentioned->id => ['role' => 'member'],
    ]);
    $author->update(['current_organization_id' => $org->id]);

    $meeting = MinutesOfMeeting::factory()->for($org)->create();

    $service = app(CommentService::class);
    $service->addComment($meeting, $author, 'Hey @Ahmad-Zaki please review this', null);

    expect(MomMention::where('mentioned_user_id', $mentioned->id)->count())->toBe(1);
});
