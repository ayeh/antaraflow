<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\AI\Models\ExtractionTemplate;
use App\Models\User;
use App\Support\Enums\ExtractionType;
use App\Support\Enums\MeetingType;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Manager->value]);
});

test('manager can list extraction templates', function () {
    ExtractionTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'name' => 'Custom Summary',
        'extraction_type' => ExtractionType::Summary,
    ]);

    $response = $this->actingAs($this->user)->get(route('extraction-templates.index'));

    $response->assertSuccessful();
    $response->assertSee('Custom Summary');
});

test('manager can view create form', function () {
    $response = $this->actingAs($this->user)->get(route('extraction-templates.create'));

    $response->assertSuccessful();
    $response->assertSee('Create Extraction Template');
});

test('manager can store extraction template', function () {
    $response = $this->actingAs($this->user)->post(route('extraction-templates.store'), [
        'name' => 'StandUp Summary',
        'meeting_type' => MeetingType::StandUp->value,
        'extraction_type' => ExtractionType::Summary->value,
        'prompt_template' => 'Summarize this standup meeting: {transcript}',
        'system_message' => 'You are a standup meeting expert.',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('extraction-templates.index'));

    $this->assertDatabaseHas('extraction_templates', [
        'name' => 'StandUp Summary',
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'extraction_type' => ExtractionType::Summary->value,
        'meeting_type' => MeetingType::StandUp->value,
    ]);
});

test('manager can store template without meeting type (wildcard)', function () {
    $response = $this->actingAs($this->user)->post(route('extraction-templates.store'), [
        'name' => 'Universal Action Items',
        'meeting_type' => null,
        'extraction_type' => ExtractionType::ActionItems->value,
        'prompt_template' => 'Extract action items from: {transcript}',
        'is_active' => true,
    ]);

    $response->assertRedirect(route('extraction-templates.index'));

    $this->assertDatabaseHas('extraction_templates', [
        'name' => 'Universal Action Items',
        'meeting_type' => null,
    ]);
});

test('manager can update extraction template', function () {
    $template = ExtractionTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'name' => 'Old Name',
    ]);

    $response = $this->actingAs($this->user)->put(route('extraction-templates.update', $template), [
        'name' => 'Updated Name',
        'extraction_type' => $template->extraction_type->value,
        'prompt_template' => 'Updated prompt: {transcript}',
        'is_active' => false,
    ]);

    $response->assertRedirect(route('extraction-templates.index'));

    $this->assertDatabaseHas('extraction_templates', [
        'id' => $template->id,
        'name' => 'Updated Name',
        'is_active' => false,
    ]);
});

test('manager can delete extraction template', function () {
    $template = ExtractionTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($this->user)->delete(route('extraction-templates.destroy', $template));

    $response->assertRedirect(route('extraction-templates.index'));
    $this->assertDatabaseMissing('extraction_templates', ['id' => $template->id]);
});

test('viewer cannot create extraction template', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $response = $this->actingAs($viewer)->post(route('extraction-templates.store'), [
        'name' => 'Should Fail',
        'extraction_type' => ExtractionType::Summary->value,
        'prompt_template' => 'Test prompt: {transcript}',
    ]);

    $response->assertForbidden();
});

test('viewer cannot delete extraction template', function () {
    $viewer = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($viewer, ['role' => UserRole::Viewer->value]);

    $template = ExtractionTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $response = $this->actingAs($viewer)->delete(route('extraction-templates.destroy', $template));

    $response->assertForbidden();
});

test('store validation requires prompt template', function () {
    $response = $this->actingAs($this->user)->post(route('extraction-templates.store'), [
        'name' => 'Missing Prompt',
        'extraction_type' => ExtractionType::Summary->value,
        'prompt_template' => '',
    ]);

    $response->assertSessionHasErrors('prompt_template');
});

test('store validation requires valid extraction type', function () {
    $response = $this->actingAs($this->user)->post(route('extraction-templates.store'), [
        'name' => 'Bad Type',
        'extraction_type' => 'invalid_type',
        'prompt_template' => 'Some prompt: {transcript}',
    ]);

    $response->assertSessionHasErrors('extraction_type');
});

test('template renders prompt with transcript', function () {
    $template = ExtractionTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'prompt_template' => 'Summarize this: {transcript}',
    ]);

    expect($template->renderPrompt('Hello world'))->toBe('Summarize this: Hello world');
});
