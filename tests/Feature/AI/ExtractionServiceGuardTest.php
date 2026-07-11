<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Admin\Services\AiControlService;
use App\Domain\AI\Services\ExtractionService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Infrastructure\AI\Exceptions\AiDisabledException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('extraction service refuses to run when AI is disabled', function () {
    app(AiControlService::class)->disable();

    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
    ]);

    expect(fn () => app(ExtractionService::class)->extractAll($mom))
        ->toThrow(AiDisabledException::class);
});
