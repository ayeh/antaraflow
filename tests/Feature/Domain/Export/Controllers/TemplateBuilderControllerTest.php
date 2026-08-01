<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Export\Models\ExportTemplate;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->members()->attach($this->user->id, ['role' => 'admin']);

    $this->template = ExportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'name' => 'My Builder Template',
        'blocks' => null,
        'is_system' => false,
    ]);
});

// ── Builder show page ─────────────────────────────────────────────────────────

it('shows the builder view for an org template', function (): void {
    $this->actingAs($this->user)
        ->get(route('settings.export-templates.builder', $this->template))
        ->assertOk()
        ->assertViewIs('settings.export-templates.builder')
        ->assertViewHas('template', $this->template)
        ->assertViewHas('blockTypes');
});

it('redirects system templates to index with info message', function (): void {
    $system = ExportTemplate::factory()->create([
        'organization_id' => null,
        'is_system' => true,
        'name' => 'System Preset',
    ]);

    $this->actingAs($this->user)
        ->get(route('settings.export-templates.builder', $system))
        ->assertRedirect(route('settings.export-templates.index'));
});

it('returns 403 for a template from another org', function (): void {
    $otherOrg = Organization::factory()->create();
    $otherTemplate = ExportTemplate::factory()->create(['organization_id' => $otherOrg->id]);

    $this->actingAs($this->user)
        ->get(route('settings.export-templates.builder', $otherTemplate))
        ->assertForbidden();
});

// ── Save blocks ───────────────────────────────────────────────────────────────

it('saves blocks JSON to the template', function (): void {
    $blocks = [
        ['id' => 'b1', 'type' => 'title',  'settings' => ['text' => '{{title}}', 'align' => 'center']],
        ['id' => 'b2', 'type' => 'footer', 'settings' => ['text' => '', 'align' => 'center']],
    ];

    $this->actingAs($this->user)
        ->postJson(route('settings.export-templates.blocks.save', $this->template), [
            'blocks' => $blocks,
            'primary_color' => '#4c1d95',
            'font_family' => 'Arial',
        ])
        ->assertOk()
        ->assertJsonFragment(['ok' => true]);

    $this->template->refresh();
    expect($this->template->blocks)->toHaveCount(2);
    expect($this->template->blocks[0]['type'])->toBe('title');
});

it('saves primary_color and font_family', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('settings.export-templates.blocks.save', $this->template), [
            'blocks' => [],
            'primary_color' => '#ff0000',
            'font_family' => 'Calibri',
        ])
        ->assertOk();

    $this->template->refresh();
    expect($this->template->primary_color)->toBe('#ff0000');
    expect($this->template->font_family)->toBe('Calibri');
});

it('rejects save on another org template with 403', function (): void {
    $otherOrg = Organization::factory()->create();
    $other = ExportTemplate::factory()->create(['organization_id' => $otherOrg->id]);

    $this->actingAs($this->user)
        ->postJson(route('settings.export-templates.blocks.save', $other), [
            'blocks' => [],
        ])
        ->assertForbidden();
});

// ── Preview ───────────────────────────────────────────────────────────────────

it('preview returns HTML when a MOM exists', function (): void {
    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Preview Test Meeting',
    ]);

    $this->actingAs($this->user)
        ->post(route('settings.export-templates.preview', $this->template), [
            'blocks_json' => json_encode([
                ['id' => 'b1', 'type' => 'title', 'settings' => ['text' => 'PREVIEW', 'align' => 'center']],
            ]),
            'language' => 'ms',
        ])
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('PREVIEW');
});

it('preview returns a placeholder when no MOM exists', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.export-templates.preview', $this->template), [
            'language' => 'ms',
        ])
        ->assertOk()
        ->assertSee('Tiada minit mesyuarat');
});

it('preview decodes blocks_json and uses them over saved blocks', function (): void {
    $this->template->update(['blocks' => [
        ['id' => 'saved', 'type' => 'title', 'settings' => ['text' => 'SAVED_TITLE', 'align' => 'left']],
    ]]);

    MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('settings.export-templates.preview', $this->template), [
            'blocks_json' => json_encode([
                ['id' => 'live', 'type' => 'title', 'settings' => ['text' => 'LIVE_UNSAVED', 'align' => 'left']],
            ]),
            'language' => 'ms',
        ])
        ->assertOk()
        ->assertSee('LIVE_UNSAVED')
        ->assertDontSee('SAVED_TITLE');
});

// ── Logo upload ───────────────────────────────────────────────────────────────

it('uploads a logo and stores its path on the template', function (): void {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('logo.png', 200, 80);

    $this->actingAs($this->user)
        ->post(route('settings.export-templates.logo', $this->template), [
            'logo' => $file,
        ])
        ->assertOk()
        ->assertJsonFragment(['ok' => true])
        ->assertJsonStructure(['logo_url', 'logo_path']);

    $this->template->refresh();
    expect($this->template->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($this->template->logo_path);
});

it('rejects non-image logo uploads', function (): void {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('malware.exe', 100);

    $this->actingAs($this->user)
        ->post(route('settings.export-templates.logo', $this->template), [
            'logo' => $file,
        ])
        ->assertSessionHasErrors('logo');
});

// ── Duplicate ─────────────────────────────────────────────────────────────────

it('duplicates a template for the current org', function (): void {
    $this->template->update(['blocks' => [
        ['id' => 'b1', 'type' => 'title', 'settings' => ['text' => 'Test', 'align' => 'center']],
    ]]);

    $this->actingAs($this->user)
        ->post(route('settings.export-templates.duplicate', $this->template))
        ->assertRedirect();

    $copy = ExportTemplate::where('name', 'My Builder Template (Salinan)')->first();
    expect($copy)->not->toBeNull();
    expect($copy->organization_id)->toBe($this->org->id);
    expect($copy->is_system)->toBeFalse();
    expect($copy->blocks)->toHaveCount(1);
});

it('can duplicate a system preset into the current org', function (): void {
    $system = ExportTemplate::factory()->create([
        'organization_id' => null,
        'is_system' => true,
        'name' => 'MPIK Standard',
        'blocks' => [['id' => 'b1', 'type' => 'title', 'settings' => []]],
    ]);

    $this->actingAs($this->user)
        ->post(route('settings.export-templates.duplicate', $system))
        ->assertRedirect();

    $copy = ExportTemplate::where('name', 'MPIK Standard (Salinan)')
        ->where('organization_id', $this->org->id)
        ->first();

    expect($copy)->not->toBeNull();
    expect($copy->is_system)->toBeFalse();
});
