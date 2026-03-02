<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Services\MomNumberService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates first MOM number for organization', function () {
    $org = Organization::factory()->create();
    $service = new MomNumberService;
    $number = $service->generate($org->id);

    expect($number)->toBe('MOM-'.date('Y').'-000001');
});

it('generates sequential MOM numbers', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    MinutesOfMeeting::factory()->create([
        'organization_id' => $org->id,
        'created_by' => $user->id,
        'mom_number' => 'MOM-'.date('Y').'-000001',
    ]);

    $service = new MomNumberService;
    $number = $service->generate($org->id);

    expect($number)->toBe('MOM-'.date('Y').'-000002');
});

it('generates numbers scoped to organization', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user1 = User::factory()->create(['current_organization_id' => $org1->id]);
    $user2 = User::factory()->create(['current_organization_id' => $org2->id]);

    MinutesOfMeeting::factory()->create([
        'organization_id' => $org1->id,
        'created_by' => $user1->id,
        'mom_number' => 'MOM-'.date('Y').'-000005',
    ]);

    $service = new MomNumberService;

    // Org2 should still start at 000001
    expect($service->generate($org2->id))->toBe('MOM-'.date('Y').'-000001');
    // Org1 should continue at 000006
    expect($service->generate($org1->id))->toBe('MOM-'.date('Y').'-000006');
});
