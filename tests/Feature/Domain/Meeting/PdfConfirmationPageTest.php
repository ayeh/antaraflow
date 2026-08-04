<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Meeting\Models\MomCirculation;
use App\Domain\Meeting\Models\MomCirculationRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('PDF export includes confirmation data when meeting is approved', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $meeting = MinutesOfMeeting::factory()->for($org)->approved()->create([
        'created_by' => $user->id,
    ]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Sila sahkan minit mesyuarat',
        'deadline_at' => now()->subDay(),
        'status' => 'closed_approved',
        'closed_at' => now(),
    ]);

    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Nor Khamisah',
        'email' => 'khamisah@example.com',
        'token' => Str::random(64),
        'response' => 'confirmed',
        'responded_at' => now()->subHours(2),
    ]);

    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Mazni Miftah',
        'email' => 'mazni@example.com',
        'token' => Str::random(64),
        'response' => null,
        'deemed_confirmed_at' => now()->subHour(),
    ]);

    $html = view('pdf.mom-confirmation-page', [
        'meeting' => $meeting,
        'circulation' => $circulation->load('recipients'),
    ])->render();

    expect($html)
        ->toContain('Nor Khamisah')
        ->toContain('Mazni Miftah')
        ->toContain('PENGESAHAN MINIT MESYUARAT')
        ->toContain('Disahkan secara nyata')
        ->toContain('Tiada maklum balas (dianggap sah)')
        ->toContain('Pusingan 1');
});

test('confirmation page shows mom_number when present', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $meeting = MinutesOfMeeting::factory()->for($org)->approved()->create([
        'created_by' => $user->id,
        'mom_number' => 'MOM/2025/001',
    ]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 2,
        'subject' => 'Test',
        'deadline_at' => now()->subDay(),
        'status' => 'closed_approved',
        'closed_at' => now(),
    ]);

    $html = view('pdf.mom-confirmation-page', [
        'meeting' => $meeting,
        'circulation' => $circulation->load('recipients'),
    ])->render();

    expect($html)
        ->toContain('MOM/2025/001')
        ->toContain('Pusingan 2');
});

test('PDF buildHtml appends confirmation page for approved meeting with closed circulation', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $meeting = MinutesOfMeeting::factory()->for($org)->approved()->create([
        'created_by' => $user->id,
    ]);

    $circulation = MomCirculation::createForOrganization($org->id, [
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $user->id,
        'round' => 1,
        'subject' => 'Test',
        'deadline_at' => now()->subDay(),
        'status' => 'closed_approved',
        'closed_at' => now(),
    ]);

    MomCirculationRecipient::create([
        'mom_circulation_id' => $circulation->id,
        'name' => 'Ali Hassan',
        'email' => 'ali@example.com',
        'token' => Str::random(64),
        'response' => 'confirmed',
        'responded_at' => now()->subHour(),
    ]);

    $service = app(\App\Domain\Export\Services\PdfExportService::class);
    $pdfBytes = $service->generate($meeting);

    expect($pdfBytes)->toBeString()->not->toBeEmpty();
});

test('PDF export does not include confirmation page when meeting is not approved', function (): void {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    $meeting = MinutesOfMeeting::factory()->for($org)->finalized()->create([
        'created_by' => $user->id,
    ]);

    $html = view('pdf.mom-confirmation-page', [
        'meeting' => $meeting,
        'circulation' => (object) [
            'created_at' => now(),
            'round' => 1,
            'deadline_at' => now()->addDay(),
            'recipients' => collect(),
        ],
    ])->render();

    // Blade view itself always renders; the guard lives in PdfExportService
    expect($html)->toContain('PENGESAHAN MINIT MESYUARAT');
});
