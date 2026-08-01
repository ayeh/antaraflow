<?php

declare(strict_types=1);

use App\Domain\Account\Models\Organization;
use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Export\Models\ExportTemplate;
use App\Domain\Export\Services\TemplateRenderService;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpWord\PhpWord;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->service = app(TemplateRenderService::class);
    $this->org = Organization::factory()->create();
    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->mom = MinutesOfMeeting::factory()->create([
        'organization_id' => $this->org->id,
        'created_by' => $this->user->id,
        'title' => 'Test Meeting',
        'location' => 'Bilik Mesyuarat',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);
});

// ── Template resolution ───────────────────────────────────────────────────────

it('resolves a pinned template from mom.export_template_id', function (): void {
    $template = ExportTemplate::factory()->create(['organization_id' => $this->org->id]);
    $this->mom->update(['export_template_id' => $template->id]);

    $resolved = $this->service->resolveTemplate($this->mom->fresh());

    expect($resolved->id)->toBe($template->id);
});

it('resolves the org default when no template is pinned', function (): void {
    $default = ExportTemplate::factory()
        ->default()
        ->create(['organization_id' => $this->org->id, 'is_system' => false]);

    $resolved = $this->service->resolveTemplate($this->mom);

    expect($resolved->id)->toBe($default->id);
});

it('falls back to a system preset when no org template exists', function (): void {
    // Create a system preset without an org
    $preset = ExportTemplate::factory()->create([
        'organization_id' => null,
        'is_system' => true,
        'name' => 'Government Standard',
    ]);

    $resolved = $this->service->resolveTemplate($this->mom);

    expect($resolved->id)->toBe($preset->id);
});

it('returns a minimal in-memory template when nothing else exists', function (): void {
    $resolved = $this->service->resolveTemplate($this->mom);

    // No ID means it's the ephemeral fallback
    expect($resolved->id)->toBeNull();
    expect($resolved->is_system)->toBeTrue();
});

it('pinned template takes priority over org default', function (): void {
    $default = ExportTemplate::factory()
        ->default()
        ->create(['organization_id' => $this->org->id, 'is_system' => false]);

    $pinned = ExportTemplate::factory()
        ->create(['organization_id' => $this->org->id]);

    $this->mom->update(['export_template_id' => $pinned->id]);

    $resolved = $this->service->resolveTemplate($this->mom->fresh());

    expect($resolved->id)->toBe($pinned->id);
});

// ── renderHtml ────────────────────────────────────────────────────────────────

it('renderHtml returns a valid HTML string', function (): void {
    $template = ExportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'blocks' => [
            ['id' => 'b1', 'type' => 'title', 'settings' => ['text' => '{{title}}', 'align' => 'center']],
            ['id' => 'b2', 'type' => 'meta',  'settings' => []],
            ['id' => 'b3', 'type' => 'footer', 'settings' => ['text' => '', 'align' => 'center']],
        ],
    ]);

    $html = $this->service->renderHtml($this->mom, $template, 'ms');

    expect($html)
        ->toContain('<!DOCTYPE html>')
        ->toContain('Test Meeting')
        ->toContain('Bilik Mesyuarat');
});

it('renderHtml uses output language for labels', function (): void {
    $template = ExportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'blocks' => [
            ['id' => 'b1', 'type' => 'attendees', 'settings' => ['groups' => ['present']]],
        ],
    ]);

    // Create an attendee so the renderer has something to render
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $this->mom->id,
        'attendance_group' => 'present',
        'user_id' => null,
        'name' => 'Peserta A',
    ]);

    $htmlMs = $this->service->renderHtml($this->mom, $template, 'ms');
    $htmlEn = $this->service->renderHtml($this->mom, $template, 'en');

    expect($htmlMs)->toContain('Hadir');
    expect($htmlEn)->toContain('Present');
});

it('renderHtml falls back to legacy blocks when template has no blocks', function (): void {
    $template = ExportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'blocks' => null,
    ]);

    $html = $this->service->renderHtml($this->mom, $template, 'ms');

    expect($html)->toContain('<!DOCTYPE html>');
});

it('renderHtml skips unknown block types gracefully', function (): void {
    $template = ExportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'blocks' => [
            ['id' => 'b1', 'type' => 'unknown_block_xyz', 'settings' => []],
            ['id' => 'b2', 'type' => 'title', 'settings' => ['text' => 'OK', 'align' => 'left']],
        ],
    ]);

    $html = $this->service->renderHtml($this->mom, $template, 'ms');

    expect($html)->toContain('OK');
});

// ── renderWord ────────────────────────────────────────────────────────────────

it('renderWord returns a PhpWord instance', function (): void {
    $template = ExportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'blocks' => [
            ['id' => 'b1', 'type' => 'title',  'settings' => ['text' => '{{title}}', 'align' => 'center']],
            ['id' => 'b2', 'type' => 'footer', 'settings' => ['text' => '', 'align' => 'center']],
        ],
    ]);

    $word = $this->service->renderWord($this->mom, $template, 'ms');

    expect($word)->toBeInstanceOf(PhpWord::class);
});

it('renderWord sets default font from template', function (): void {
    $template = ExportTemplate::factory()->create([
        'organization_id' => $this->org->id,
        'font_family' => 'Calibri',
        'blocks' => [
            ['id' => 'b1', 'type' => 'title', 'settings' => ['text' => 'X', 'align' => 'left']],
        ],
    ]);

    $word = $this->service->renderWord($this->mom, $template, 'ms');

    expect($word->getDefaultFontName())->toBe('Calibri');
});

// ── Org scope isolation ───────────────────────────────────────────────────────

it('does not resolve a template from a different organisation', function (): void {
    $otherOrg = Organization::factory()->create();
    ExportTemplate::factory()->default()->create([
        'organization_id' => $otherOrg->id,
        'is_system' => false,
    ]);

    // No templates for $this->org, so fallback should be the minimal template.
    $resolved = $this->service->resolveTemplate($this->mom);

    expect($resolved->organization_id)->not->toBe($otherOrg->id);
});
