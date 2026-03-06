<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Report\Models\ReportTemplate;
use App\Models\User;
use App\Support\Enums\ReportType;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Admin->value]);
});

test('admin can list report templates', function () {
    ReportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'name' => 'Monthly Board Summary',
        'type' => ReportType::MonthlySummary,
    ]);

    $response = $this->actingAs($this->user)->get(route('reports.index'));

    $response->assertSuccessful();
    $response->assertSee('Monthly Board Summary');
});

test('admin can view create form', function () {
    $response = $this->actingAs($this->user)->get(route('reports.create'));

    $response->assertSuccessful();
    $response->assertSee('Create Report Template');
});

test('admin can store report template', function () {
    $response = $this->actingAs($this->user)->post(route('reports.store'), [
        'name' => 'Q1 Summary Report',
        'type' => ReportType::MonthlySummary->value,
        'schedule' => '0 9 1 * *',
        'recipients' => ['alice@example.com', 'bob@example.com'],
        'is_active' => true,
    ]);

    $response->assertRedirect(route('reports.index'));

    $this->assertDatabaseHas('report_templates', [
        'name' => 'Q1 Summary Report',
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'type' => ReportType::MonthlySummary->value,
        'schedule' => '0 9 1 * *',
    ]);
});

test('admin can update report template', function () {
    $template = ReportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'name' => 'Old Name',
        'type' => ReportType::MonthlySummary,
    ]);

    $response = $this->actingAs($this->user)->put(route('reports.update', $template), [
        'name' => 'Updated Name',
        'type' => ReportType::ActionItemStatus->value,
        'is_active' => false,
    ]);

    $response->assertRedirect(route('reports.index'));

    $this->assertDatabaseHas('report_templates', [
        'id' => $template->id,
        'name' => 'Updated Name',
        'type' => ReportType::ActionItemStatus->value,
        'is_active' => false,
    ]);
});

test('admin can delete report template', function () {
    $template = ReportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('reports.destroy', $template));

    $response->assertRedirect(route('reports.index'));
    $this->assertDatabaseMissing('report_templates', ['id' => $template->id]);
});

test('viewer cannot access report templates', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $response = $this->actingAs($viewer)->get(route('reports.index'));

    $response->assertForbidden();
});

test('viewer cannot create report template', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $response = $this->actingAs($viewer)->post(route('reports.store'), [
        'name' => 'Should Fail',
        'type' => ReportType::MonthlySummary->value,
    ]);

    $response->assertForbidden();
});

test('validation rejects invalid report type', function () {
    $response = $this->actingAs($this->user)->post(route('reports.store'), [
        'name' => 'Bad Type',
        'type' => 'invalid_type',
    ]);

    $response->assertSessionHasErrors('type');
});

test('validation rejects invalid recipient email', function () {
    $response = $this->actingAs($this->user)->post(route('reports.store'), [
        'name' => 'Test Report',
        'type' => ReportType::MonthlySummary->value,
        'recipients' => ['not-an-email'],
    ]);

    $response->assertSessionHasErrors('recipients.0');
});
