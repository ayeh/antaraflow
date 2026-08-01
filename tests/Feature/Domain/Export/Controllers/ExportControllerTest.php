<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Export\Models\ExportTemplate;
use App\Domain\Export\Services\MomTranslationService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use App\Support\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user, ['role' => UserRole::Member->value]);
    $this->meeting = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'language' => 'ms',
    ]);
});

test('user can export meeting as pdf', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.export.pdf', $this->meeting));

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});

test('user can export meeting action items as csv', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.export.csv', $this->meeting));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('text/csv');
});

test('user can export meeting as word document', function () {
    $response = $this->actingAs($this->user)->get(route('meetings.export.word', $this->meeting));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('wordprocessingml');
});

test('unauthenticated user cannot export', function () {
    $response = $this->get(route('meetings.export.pdf', $this->meeting));

    $response->assertRedirect(route('login'));
});

// ── Template and language override via query params ───────────────────────────

test('pdf export accepts template_id query param and uses it', function () {
    $template = ExportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'blocks' => [
            ['id' => 'b1', 'type' => 'title', 'settings' => ['text' => '{{title}}', 'align' => 'center']],
        ],
    ]);

    $response = $this->actingAs($this->user)->get(
        route('meetings.export.pdf', $this->meeting).'?template_id='.$template->id
    );

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});

test('pdf export accepts language query param', function () {
    $response = $this->actingAs($this->user)->get(
        route('meetings.export.pdf', $this->meeting).'?language=en'
    );

    $response->assertSuccessful();
    $response->assertHeader('content-type', 'application/pdf');
});

test('pdf export ignores invalid language query param', function () {
    $response = $this->actingAs($this->user)->get(
        route('meetings.export.pdf', $this->meeting).'?language=fr'
    );

    $response->assertSuccessful();
    // Falls through — meeting's original language is used
});

test('word export accepts template_id and language query params', function () {
    $template = ExportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'blocks' => [
            ['id' => 'b1', 'type' => 'title', 'settings' => ['text' => '{{title}}', 'align' => 'left']],
        ],
    ]);

    $response = $this->actingAs($this->user)->get(
        route('meetings.export.word', $this->meeting).'?template_id='.$template->id.'&language=en'
    );

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('wordprocessingml');
});

test('pdf export with language matching source skips translation', function () {
    // Bind a mock translator that must NOT call applyTranslations
    $translator = Mockery::mock(MomTranslationService::class);
    $translator->shouldReceive('needsTranslation')->andReturn(false);
    $translator->shouldNotReceive('applyTranslations');
    app()->instance(MomTranslationService::class, $translator);

    $response = $this->actingAs($this->user)->get(
        route('meetings.export.pdf', $this->meeting).'?language=ms'
    );

    $response->assertSuccessful();
});

test('pdf export with different output language triggers translation attempt', function () {
    // Bind a mock translator that SHOULD call applyTranslations
    $translator = Mockery::mock(MomTranslationService::class);
    $translator->shouldReceive('needsTranslation')->andReturn(true);
    $translator->shouldReceive('applyTranslations')->once();
    app()->instance(MomTranslationService::class, $translator);

    $response = $this->actingAs($this->user)->get(
        route('meetings.export.pdf', $this->meeting).'?language=en'
    );

    $response->assertSuccessful();
});
