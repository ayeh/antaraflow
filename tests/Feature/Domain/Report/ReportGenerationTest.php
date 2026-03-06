<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\ActionItem\Models\ActionItem;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Domain\Report\Generators\ActionItemStatusGenerator;
use App\Domain\Report\Generators\MonthlySummaryGenerator;
use App\Domain\Report\Jobs\GenerateReportJob;
use App\Domain\Report\Models\GeneratedReport;
use App\Domain\Report\Models\ReportTemplate;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Admin->value]);
});

test('monthly summary generator produces PDF file', function () {
    $template = ReportTemplate::factory()->monthlySummary()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'filters' => [
            'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
            'end_date' => now()->subMonth()->endOfMonth()->toDateString(),
        ],
    ]);

    MinutesOfMeeting::factory()->count(3)->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'meeting_date' => now()->subMonth()->startOfMonth()->addDays(5),
        'duration_minutes' => 60,
    ]);

    Storage::fake('local');

    $generator = app(MonthlySummaryGenerator::class);
    $filePath = $generator->generate($template);

    expect($filePath)->toContain('monthly-summary');
    Storage::disk('local')->assertExists($filePath);
});

test('action item status generator produces PDF file', function () {
    $template = ReportTemplate::factory()->actionItemStatus()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    ActionItem::factory()->count(3)->open()->create([
        'organization_id' => $this->org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'assigned_to' => $this->user->id,
        'created_by' => $this->user->id,
    ]);

    Storage::fake('local');

    $generator = app(ActionItemStatusGenerator::class);
    $filePath = $generator->generate($template);

    expect($filePath)->toContain('action-item-status');
    Storage::disk('local')->assertExists($filePath);
});

test('on-demand generate dispatches job', function () {
    Bus::fake();

    $template = ReportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->post(route('reports.generate', $template));

    $response->assertRedirect(route('reports.index'));
    Bus::assertDispatched(GenerateReportJob::class);
});

test('generated report appears in history', function () {
    $template = ReportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    GeneratedReport::factory()->create([
        'report_template_id' => $template->id,
        'organization_id' => $this->org->id,
        'generated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('reports.generated.index'));

    $response->assertSuccessful();
    $response->assertSee($template->name);
});

test('download returns file', function () {
    Storage::fake('local');

    $template = ReportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $filePath = 'reports/test-report.pdf';
    Storage::disk('local')->put($filePath, 'fake pdf content');

    $report = GeneratedReport::factory()->create([
        'report_template_id' => $template->id,
        'organization_id' => $this->org->id,
        'file_path' => $filePath,
        'generated_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->get(route('reports.generated.download', $report));

    $response->assertSuccessful();
    $response->assertDownload('test-report.pdf');
});

test('scheduled command finds due templates', function () {
    Bus::fake();

    ReportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'is_active' => true,
        'schedule' => '* * * * *',
        'last_generated_at' => null,
    ]);

    ReportTemplate::factory()->inactive()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'schedule' => '* * * * *',
    ]);

    $this->artisan('reports:generate-scheduled')
        ->assertSuccessful();

    Bus::assertDispatched(GenerateReportJob::class, 1);
});
