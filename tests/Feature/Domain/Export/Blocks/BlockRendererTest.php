<?php

declare(strict_types=1);

use App\Domain\Attendee\Models\MomAttendee;
use App\Domain\Export\Blocks\ExportContext;
use App\Domain\Export\Blocks\Renderers\ActionItemsRenderer;
use App\Domain\Export\Blocks\Renderers\ActionTagRenderer;
use App\Domain\Export\Blocks\Renderers\AttendeesRenderer;
use App\Domain\Export\Blocks\Renderers\DividerRenderer;
use App\Domain\Export\Blocks\Renderers\FooterRenderer;
use App\Domain\Export\Blocks\Renderers\LetterheadRenderer;
use App\Domain\Export\Blocks\Renderers\MetaRenderer;
use App\Domain\Export\Blocks\Renderers\PageBreakRenderer;
use App\Domain\Export\Blocks\Renderers\RichTextRenderer;
use App\Domain\Export\Blocks\Renderers\SignatureRenderer;
use App\Domain\Export\Blocks\Renderers\TitleRenderer;
use App\Domain\Export\Models\ExportTemplate;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Build a minimal ExportContext around a freshly-created MOM.
 *
 * @param  array<string, mixed>  $templateAttrs
 */
function makeCtx(array $templateAttrs = [], string $lang = 'ms'): ExportContext
{
    $mom = MinutesOfMeeting::factory()->create([
        'title' => 'Mesyuarat Pengurusan Bil. 5/2026',
        'mom_number' => '5',
        'start_time' => '09:00',
        'end_time' => '11:00',
        'location' => 'Bilik Mesyuarat Utama',
        'prepared_by' => 'En. Ahmad',
        'language' => 'ms',
    ]);

    $template = new ExportTemplate(array_merge([
        'name' => 'Test',
        'primary_color' => '#4c1d95',
        'font_family' => 'Arial',
    ], $templateAttrs));

    return new ExportContext($mom, $template, $lang);
}

// ── LetterheadRenderer ────────────────────────────────────────────────────────

it('letterhead renders static organization line', function (): void {
    $ctx = makeCtx();
    $html = (new LetterheadRenderer)->toHtml([
        'align' => 'center',
        'lines' => ['I-KPKT'],
    ], $ctx);

    expect($html)->toContain('I-KPKT')
        ->and($html)->toContain('text-align:center');
});

it('letterhead type() returns letterhead', function (): void {
    expect((new LetterheadRenderer)->type())->toBe('letterhead');
});

// ── TitleRenderer ─────────────────────────────────────────────────────────────

it('title renderer resolves {{title}} token', function (): void {
    $ctx = makeCtx();
    $html = (new TitleRenderer)->toHtml([
        'text' => 'MINIT MESYUARAT {{title}}',
        'align' => 'center',
    ], $ctx);

    expect($html)->toContain('Mesyuarat Pengurusan Bil. 5/2026');
});

it('title renderer centers when align=center', function (): void {
    $ctx = makeCtx();
    $html = (new TitleRenderer)->toHtml(['text' => 'Test', 'align' => 'center'], $ctx);

    expect($html)->toContain('center');
});

// ── MetaRenderer ──────────────────────────────────────────────────────────────

it('meta renderer includes venue', function (): void {
    $ctx = makeCtx();
    $html = (new MetaRenderer)->toHtml([], $ctx);

    expect($html)->toContain('Bilik Mesyuarat Utama');
});

it('meta renderer formats start time as 12-hour AM/PM', function (): void {
    $ctx = makeCtx();
    $html = (new MetaRenderer)->toHtml([], $ctx);

    // TokenResolver: Carbon::parse('09:00')->format('g:i A') → '9:00 AM'
    expect($html)->toContain('9:00 AM');
});

it('meta renderer uses ms date and time labels', function (): void {
    $ctx = makeCtx(lang: 'ms');
    $html = (new MetaRenderer)->toHtml([], $ctx);

    expect($html)->toContain('Tarikh');
});

// ── AttendeesRenderer ─────────────────────────────────────────────────────────

it('attendees renderer lists present members', function (): void {
    $ctx = makeCtx();
    // Create two 'present' attendees for this MOM
    MomAttendee::factory()->count(2)->create([
        'minutes_of_meeting_id' => $ctx->mom->id,
        'attendance_group' => 'present',
        'user_id' => null,
        'name' => 'Puan Siti',
    ]);
    // Reload the relationship
    $ctx->mom->load('attendees');

    $html = (new AttendeesRenderer)->toHtml(['groups' => ['present']], $ctx);

    expect($html)->toContain('Hadir')
        ->and($html)->toContain('Puan Siti');
});

it('attendees renderer includes turut hadir section', function (): void {
    $ctx = makeCtx();
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $ctx->mom->id,
        'attendance_group' => 'also_present',
        'user_id' => null,
        'name' => 'Encik Ali',
    ]);
    $ctx->mom->load('attendees');

    $html = (new AttendeesRenderer)->toHtml(
        ['groups' => ['present', 'also_present']],
        $ctx
    );

    expect($html)->toContain('Turut Hadir')
        ->and($html)->toContain('Encik Ali');
});

it('attendees renderer returns empty string when no attendees exist', function (): void {
    $ctx = makeCtx();
    $html = (new AttendeesRenderer)->toHtml(['groups' => ['present']], $ctx);

    expect($html)->toBe('');
});

it('attendees renderer skips groups not listed in settings', function (): void {
    $ctx = makeCtx();
    MomAttendee::factory()->create([
        'minutes_of_meeting_id' => $ctx->mom->id,
        'attendance_group' => 'absent',
        'user_id' => null,
        'name' => 'Tidak Hadir Orang',
    ]);
    $ctx->mom->load('attendees');

    $html = (new AttendeesRenderer)->toHtml(['groups' => ['present']], $ctx);

    expect($html)->not->toContain('Tidak Hadir');
});

// ── RichTextRenderer ──────────────────────────────────────────────────────────

it('richtext renders static content', function (): void {
    $ctx = makeCtx();
    $html = (new RichTextRenderer)->toHtml(['content' => '<p>Hello world</p>', 'source' => ''], $ctx);

    expect($html)->toContain('Hello world');
});

it('richtext returns empty string when content is empty and no source', function (): void {
    $ctx = makeCtx();
    $html = (new RichTextRenderer)->toHtml(['content' => '', 'source' => ''], $ctx);

    expect($html)->toBe('');
});

// ── ActionTagRenderer ─────────────────────────────────────────────────────────

it('action tag renderer outputs the tag text', function (): void {
    $ctx = makeCtx();
    $html = (new ActionTagRenderer)->toHtml(['tag' => 'Makluman', 'align' => 'right'], $ctx);

    expect($html)->toContain('Makluman');
});

it('action tag is right-aligned', function (): void {
    $ctx = makeCtx();
    $html = (new ActionTagRenderer)->toHtml(['tag' => 'Tindakan : PJ(A)', 'align' => 'right'], $ctx);

    expect($html)->toContain('right');
});

// ── DividerRenderer ───────────────────────────────────────────────────────────

it('divider renderer emits an hr element', function (): void {
    $ctx = makeCtx();
    $html = (new DividerRenderer)->toHtml(['color' => '#e5e7eb', 'thickness' => 1], $ctx);

    expect($html)->toContain('<hr');
});

// ── PageBreakRenderer ─────────────────────────────────────────────────────────

it('page break renderer emits a visual page break indicator', function (): void {
    $ctx = makeCtx();
    $html = (new PageBreakRenderer)->toHtml([], $ctx);

    // HTML preview uses a dashed-border div with "page break" text
    expect($html)->toContain('page break');
});

// ── SignatureRenderer ─────────────────────────────────────────────────────────

it('signature renderer outputs names in uppercase', function (): void {
    $ctx = makeCtx();
    $html = (new SignatureRenderer)->toHtml([
        'columns' => [
            ['name' => 'En. Ahmad', 'position' => 'Setiausaha', 'label' => 'Disediakan oleh', 'show_date' => true],
            ['name' => 'Dato Yusof', 'position' => 'Pengerusi', 'label' => 'Disahkan oleh', 'show_date' => false],
        ],
    ], $ctx);

    // Renderer applies strtoupper() to names
    expect($html)->toContain('EN. AHMAD')
        ->and($html)->toContain('DATO YUSOF')
        ->and($html)->toContain('Setiausaha');
});

it('signature renderer emits the section heading', function (): void {
    $ctx = makeCtx();
    $html = (new SignatureRenderer)->toHtml([], $ctx);

    expect($html)->toContain('Pengesahan');
});

// ── FooterRenderer ────────────────────────────────────────────────────────────

it('footer renderer outputs custom text when provided', function (): void {
    $ctx = makeCtx();
    $html = (new FooterRenderer)->toHtml(['text' => 'Rahsia', 'align' => 'center'], $ctx);

    expect($html)->toContain('Rahsia');
});

it('footer renderer outputs auto-generated cap when text is empty', function (): void {
    $ctx = makeCtx();
    $html = (new FooterRenderer)->toHtml(['text' => '', 'align' => 'center'], $ctx);

    expect($html)->toContain('antaraNote');
});

// ── ActionItemsRenderer ───────────────────────────────────────────────────────

it('action items renderer returns a string', function (): void {
    $ctx = makeCtx();
    $html = (new ActionItemsRenderer)->toHtml([], $ctx);

    expect($html)->toBeString();
});
