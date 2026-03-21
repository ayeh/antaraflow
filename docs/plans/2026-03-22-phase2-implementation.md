# Phase 2 Full Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Complete Phase 2 — Collaboration @mentions/reactions, Export history/email/templates, Analytics pipeline, Settings (profile/notifications/security/integrations/API keys), and full API v1 coverage.

**Architecture:** Parallel foundation-first — all migrations+models in Wave 1, then backend services+controllers, then UI views and API endpoints.

**Tech Stack:** Laravel 12, PHP 8.4, Alpine.js, Tailwind CSS, Pest 4, DomPDF, PHPWord, Laravel Queues, Laravel Notifications

---

## Wave 1 — Database Foundation (Tasks 1-8)

### Task 1: mom_mentions migration + MomMention model

**Files:**
- Create: `database/migrations/2026_03_22_000001_create_mom_mentions_table.php`
- Create: `app/Domain/Collaboration/Models/MomMention.php`
- Test: `tests/Feature/Domain/Collaboration/Models/MomMentionTest.php`

**Step 1: Write failing test**
```php
<?php
declare(strict_types=1);

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MomMention;
use App\Models\User;

it('can create a mention record', function (): void {
    $user = User::factory()->create();
    $mentionedUser = User::factory()->create();
    $org = \App\Domain\Account\Models\Organization::factory()->create();
    $meeting = \App\Domain\Meeting\Models\MinutesOfMeeting::factory()->for($org)->create();
    $comment = Comment::factory()->create(['organization_id' => $org->id, 'user_id' => $user->id]);

    $mention = MomMention::create([
        'comment_id' => $comment->id,
        'mentioned_user_id' => $mentionedUser->id,
        'organization_id' => $org->id,
        'minutes_of_meeting_id' => $meeting->id,
        'is_read' => false,
    ]);

    expect($mention->is_read)->toBeFalse();
    expect(MomMention::count())->toBe(1);
});
```

**Step 2:** `php artisan test --compact --filter="can create a mention record"` → FAIL

**Step 3: Create migration**
```php
Schema::create('mom_mentions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();
    $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('minutes_of_meeting_id')->constrained('minutes_of_meetings')->cascadeOnDelete();
    $table->boolean('is_read')->default(false);
    $table->timestamp('notified_at')->nullable();
    $table->timestamps();
    $table->index(['mentioned_user_id', 'is_read']);
});
```

**Step 3: Create MomMention model**
```php
<?php
declare(strict_types=1);
namespace App\Domain\Collaboration\Models;
use App\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomMention extends Model
{
    use BelongsToOrganization;
    protected $guarded = ['id'];
    protected function casts(): array { return ['is_read' => 'boolean', 'notified_at' => 'datetime']; }
    public function comment(): BelongsTo { return $this->belongsTo(Comment::class); }
    public function mentionedUser(): BelongsTo { return $this->belongsTo(User::class, 'mentioned_user_id'); }
}
```

**Step 4:** `php artisan test --compact --filter="can create a mention record"` → PASS
**Step 5:** `vendor/bin/pint --dirty --format agent && git add -A && git commit -m "feat: add mom_mentions table and MomMention model"`

---

### Task 2: mom_reactions migration + MomReaction model

**Files:**
- Create: `database/migrations/2026_03_22_000002_create_mom_reactions_table.php`
- Create: `app/Domain/Collaboration/Models/MomReaction.php`

**Step 3: Migration**
```php
Schema::create('mom_reactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('emoji', 10);
    $table->timestamps();
    $table->unique(['comment_id', 'user_id', 'emoji']);
});
```

**Step 3: Model**
```php
<?php
declare(strict_types=1);
namespace App\Domain\Collaboration\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomReaction extends Model
{
    protected $guarded = ['id'];
    public function comment(): BelongsTo { return $this->belongsTo(Comment::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
```

**Test:** `it('enforces unique emoji per user per comment')` — try creating duplicate, expect `UniqueConstraintViolationException`

**Step 5:** `git commit -m "feat: add mom_reactions table and MomReaction model"`

---

### Task 3: mom_exports migration + MomExport model

**Files:**
- Create: `database/migrations/2026_03_22_000003_create_mom_exports_table.php`
- Create: `app/Domain/Export/Models/MomExport.php`

**Step 3: Migration**
```php
Schema::create('mom_exports', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained('minutes_of_meetings')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('format', 10); // pdf, docx, csv
    $table->string('file_path')->nullable();
    $table->unsignedBigInteger('file_size')->nullable();
    $table->timestamp('downloaded_at')->nullable();
    $table->timestamps();
    $table->index('minutes_of_meeting_id');
});
```

**Step 3: Model**
```php
<?php
declare(strict_types=1);
namespace App\Domain\Export\Models;
use App\Domain\Meeting\Models\MinutesOfMeeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MomExport extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['downloaded_at' => 'datetime']; }
    public function meeting(): BelongsTo { return $this->belongsTo(MinutesOfMeeting::class, 'minutes_of_meeting_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
```

**Step 5:** `git commit -m "feat: add mom_exports table and MomExport model"`

---

### Task 4: mom_email_distributions migration + model

**Files:**
- Create: `database/migrations/2026_03_22_000004_create_mom_email_distributions_table.php`
- Create: `app/Domain/Export/Models/MomEmailDistribution.php`

**Step 3: Migration**
```php
Schema::create('mom_email_distributions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained('minutes_of_meetings')->cascadeOnDelete();
    $table->foreignId('sent_by')->constrained('users')->cascadeOnDelete();
    $table->json('recipients');
    $table->string('subject');
    $table->text('body_note')->nullable();
    $table->string('export_format', 10)->default('pdf');
    $table->string('status', 20)->default('pending'); // pending, sent, failed
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('failed_at')->nullable();
    $table->text('error_message')->nullable();
    $table->timestamps();
});
```

**Step 3: Model**
```php
<?php
declare(strict_types=1);
namespace App\Domain\Export\Models;
use Illuminate\Database\Eloquent\Model;

class MomEmailDistribution extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array {
        return ['recipients' => 'array', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
```

**Step 5:** `git commit -m "feat: add mom_email_distributions table and model"`

---

### Task 5: export_templates migration + ExportTemplate model

**Files:**
- Create: `database/migrations/2026_03_22_000005_create_export_templates_table.php`
- Create: `app/Domain/Export/Models/ExportTemplate.php`

**Step 3: Migration**
```php
Schema::create('export_templates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->text('header_html')->nullable();
    $table->text('footer_html')->nullable();
    $table->text('css_overrides')->nullable();
    $table->string('logo_path')->nullable();
    $table->string('primary_color', 20)->nullable();
    $table->string('font_family', 100)->nullable();
    $table->boolean('is_default')->default(false);
    $table->timestamps();
    $table->softDeletes();
    $table->index('organization_id');
});
```

**Step 5:** `git commit -m "feat: add export_templates table and ExportTemplate model"`

---

### Task 6: analytics_daily_snapshots migration + model

**Files:**
- Create: `database/migrations/2026_03_22_000006_create_analytics_daily_snapshots_table.php`
- Create: `app/Domain/Analytics/Models/AnalyticsDailySnapshot.php`

**Step 3: Migration**
```php
Schema::create('analytics_daily_snapshots', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->date('snapshot_date');
    $table->unsignedInteger('total_meetings')->default(0);
    $table->unsignedInteger('total_action_items')->default(0);
    $table->unsignedInteger('completed_action_items')->default(0);
    $table->unsignedInteger('overdue_action_items')->default(0);
    $table->unsignedInteger('total_attendees')->default(0);
    $table->unsignedInteger('ai_usage_count')->default(0);
    $table->decimal('avg_meeting_duration_minutes', 8, 2)->nullable();
    $table->timestamps();
    $table->unique(['organization_id', 'snapshot_date']);
});
```

**Step 5:** `git commit -m "feat: add analytics_daily_snapshots table and model"`

---

### Task 7: analytics_events migration + model

**Files:**
- Create: `database/migrations/2026_03_22_000007_create_analytics_events_table.php`
- Create: `app/Domain/Analytics/Models/AnalyticsEvent.php`

**Step 3: Migration**
```php
Schema::create('analytics_events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('event_type', 100);
    $table->nullableMorphs('subject');
    $table->json('properties')->nullable();
    $table->timestamp('occurred_at');
    $table->index(['organization_id', 'event_type', 'occurred_at']);
});
```

**Step 3: Model** — `public $timestamps = false;` (uses `occurred_at` only)

**Step 5:** `git commit -m "feat: add analytics_events table and model"`

---

### Task 8: user_settings migration + UserSettings model

**Files:**
- Create: `database/migrations/2026_03_22_000008_create_user_settings_table.php`
- Create: `app/Domain/Account/Models/UserSettings.php`

**Step 3: Migration**
```php
Schema::create('user_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->json('notification_preferences')->nullable();
    $table->string('timezone', 100)->default('UTC');
    $table->string('locale', 10)->default('en');
    $table->boolean('two_factor_enabled')->default(false);
    $table->text('two_factor_secret')->nullable(); // encrypted
    $table->timestamps();
});
```

**Step 3: Model**
```php
<?php
declare(strict_types=1);
namespace App\Domain\Account\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array {
        return [
            'notification_preferences' => 'array',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
        ];
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
```

Run: `php artisan migrate` to apply all 8 migrations.

**Step 5:** `git commit -m "feat: add user_settings table and UserSettings model"`

---

## Wave 2 — Collaboration H (Tasks 9-12)

### Task 9: @mention parsing in CommentService + SendMentionNotificationsJob

**Files:**
- Modify: `app/Domain/Collaboration/Services/CommentService.php`
- Create: `app/Domain/Collaboration/Jobs/SendMentionNotificationsJob.php`
- Create: `app/Domain/Collaboration/Notifications/MentionedInCommentNotification.php`
- Test: `tests/Feature/Domain/Collaboration/MentionParsingTest.php`

**Step 1: Failing test**
```php
it('creates mention records for @username in comment body', function (): void {
    $org = Organization::factory()->create();
    $author = User::factory()->create(['name' => 'Author']);
    $mentioned = User::factory()->create(['name' => 'Ahmad Zaki']);
    $org->members()->attach([$author->id => ['role' => 'member'], $mentioned->id => ['role' => 'member']]);
    $author->update(['current_organization_id' => $org->id]);
    $meeting = MinutesOfMeeting::factory()->for($org)->create();

    $service = app(CommentService::class);
    $service->addComment($meeting, $author, 'Hey @Ahmad-Zaki please review this', null);

    expect(MomMention::where('mentioned_user_id', $mentioned->id)->count())->toBe(1);
});
```

**Step 3: Extend CommentService::addComment()**
After creating the comment, add:
```php
$this->parseMentions($comment, $commentable, $user);
```

Add private method:
```php
private function parseMentions(Comment $comment, Model $commentable, User $author): void
{
    preg_match_all('/@([\w-]+)/', $comment->body, $matches);
    if (empty($matches[1])) { return; }

    $meetingId = $commentable instanceof MinutesOfMeeting ? $commentable->id : null;

    foreach ($matches[1] as $handle) {
        $name = str_replace('-', ' ', $handle);
        $mentioned = \App\Models\User::query()
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $author->current_organization_id))
            ->where('name', 'like', "%{$name}%")
            ->first();

        if ($mentioned && $mentioned->id !== $author->id) {
            MomMention::create([
                'comment_id' => $comment->id,
                'mentioned_user_id' => $mentioned->id,
                'organization_id' => $author->current_organization_id,
                'minutes_of_meeting_id' => $meetingId,
                'is_read' => false,
            ]);
        }
    }

    if (MomMention::where('comment_id', $comment->id)->exists()) {
        SendMentionNotificationsJob::dispatch($comment);
    }
}
```

**Step 3: Create SendMentionNotificationsJob**
```php
<?php
declare(strict_types=1);
namespace App\Domain\Collaboration\Jobs;

use App\Domain\Collaboration\Models\Comment;
use App\Domain\Collaboration\Models\MomMention;
use App\Domain\Collaboration\Notifications\MentionedInCommentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMentionNotificationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Comment $comment) {}

    public function handle(): void
    {
        $mentions = MomMention::query()
            ->where('comment_id', $this->comment->id)
            ->whereNull('notified_at')
            ->with('mentionedUser')
            ->get();

        foreach ($mentions as $mention) {
            $prefs = $mention->mentionedUser->settings?->notification_preferences ?? [];
            $emailEnabled = $prefs['mention_in_comment']['email'] ?? true;
            $inAppEnabled = $prefs['mention_in_comment']['in_app'] ?? true;

            if ($inAppEnabled || $emailEnabled) {
                $mention->mentionedUser->notify(
                    new MentionedInCommentNotification($this->comment, $emailEnabled)
                );
            }

            $mention->update(['notified_at' => now()]);
        }
    }
}
```

**Step 3: Create MentionedInCommentNotification**
```php
<?php
declare(strict_types=1);
namespace App\Domain\Collaboration\Notifications;

use App\Domain\Collaboration\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentionedInCommentNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Comment $comment,
        public bool $sendEmail = true,
    ) {}

    public function via(object $notifiable): array
    {
        return $this->sendEmail ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You were mentioned in a comment')
            ->line('You were mentioned in a comment by '.$this->comment->user->name.'.')
            ->action('View Comment', route('meetings.show', $this->comment->commentable_id));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'mention',
            'comment_id' => $this->comment->id,
            'commenter' => $this->comment->user->name,
            'meeting_id' => $this->comment->commentable_id,
        ];
    }
}
```

**Step 5:** `git commit -m "feat: add @mention parsing in CommentService and SendMentionNotificationsJob"`

---

### Task 10: ReactionController (toggle emoji reactions)

**Files:**
- Create: `app/Domain/Collaboration/Controllers/ReactionController.php`
- Create: `app/Domain/Collaboration/Requests/ToggleReactionRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Collaboration/ReactionControllerTest.php`

**Step 1: Failing test**
```php
it('adds a reaction to a comment', function (): void {
    // setup org, user, meeting, comment
    $this->actingAs($user)
        ->postJson(route('comments.reactions.toggle', $comment), ['emoji' => '👍'])
        ->assertOk()
        ->assertJson(['action' => 'added', 'emoji' => '👍']);

    expect(MomReaction::where('comment_id', $comment->id)->count())->toBe(1);
});

it('removes reaction if already exists', function (): void {
    MomReaction::create(['comment_id' => $comment->id, 'user_id' => $user->id, 'emoji' => '👍']);

    $this->actingAs($user)
        ->postJson(route('comments.reactions.toggle', $comment), ['emoji' => '👍'])
        ->assertOk()
        ->assertJson(['action' => 'removed']);

    expect(MomReaction::count())->toBe(0);
});
```

**Step 3: Controller**
```php
public function toggle(ToggleReactionRequest $request, Comment $comment): JsonResponse
{
    $emoji = $request->validated('emoji');
    $userId = $request->user()->id;

    $existing = MomReaction::query()
        ->where('comment_id', $comment->id)
        ->where('user_id', $userId)
        ->where('emoji', $emoji)
        ->first();

    if ($existing) {
        $existing->delete();
        $action = 'removed';
    } else {
        MomReaction::create(['comment_id' => $comment->id, 'user_id' => $userId, 'emoji' => $emoji]);
        $action = 'added';
    }

    $count = MomReaction::query()->where('comment_id', $comment->id)->where('emoji', $emoji)->count();
    return response()->json(['action' => $action, 'emoji' => $emoji, 'count' => $count]);
}
```

**Route:** `Route::post('comments/{comment}/reactions', [ReactionController::class, 'toggle'])->name('comments.reactions.toggle');`

**Step 5:** `git commit -m "feat: add ReactionController for emoji reaction toggling"`

---

### Task 11: NotificationController (list + mark as read)

**Files:**
- Create: `app/Domain/Account/Controllers/NotificationController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Account/NotificationControllerTest.php`

**Step 3: Controller**
```php
public function index(Request $request): JsonResponse
{
    $notifications = $request->user()->notifications()->latest()->take(20)->get();
    return response()->json($notifications);
}

public function markAsRead(Request $request): JsonResponse
{
    $request->user()->unreadNotifications()->update(['read_at' => now()]);
    return response()->json(['message' => 'All notifications marked as read.']);
}

public function unreadCount(Request $request): JsonResponse
{
    return response()->json(['count' => $request->user()->unreadNotifications()->count()]);
}
```

**Routes:**
```php
Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('notifications/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.mark-read');
Route::get('notifications/count', [NotificationController::class, 'unreadCount'])->name('notifications.count');
```

**Step 5:** `git commit -m "feat: add NotificationController for in-app notifications"`

---

### Task 12: Update comments.blade.php — reaction bar + mention highlights + notification bell in navbar

**Files:**
- Modify: `resources/views/collaboration/comments.blade.php`
- Modify: `resources/views/layouts/partials/header.blade.php` (or sidebar)

**What to add:**

In `comments.blade.php` — after each comment body, add reaction bar:
```blade
<div class="flex items-center gap-1 mt-2" x-data="{ reactions: {{ json_encode($comment->reactions ?? []) }} }">
    @foreach(['👍', '❤️', '😂', '😮', '😢', '🎉'] as $emoji)
    <button
        @click="toggleReaction('{{ $emoji }}', {{ $comment->id }})"
        class="text-xs px-1.5 py-0.5 rounded-full border hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
    >{{ $emoji }} <span x-text="reactionCount('{{ $emoji }}')"></span></button>
    @endforeach
</div>
```

Render `@username` mentions as blue spans using `Str::replaceMatches` in a Blade directive or helper.

In `header.blade.php` — add notification bell:
```blade
<div x-data="{ count: 0 }" x-init="fetch('/notifications/count').then(r=>r.json()).then(d=>count=d.count)">
    <a href="{{ route('notifications.index') }}" class="relative">
        <svg ...bell icon.../>
        <template x-if="count > 0">
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center" x-text="count > 9 ? '9+' : count"></span>
        </template>
    </a>
</div>
```

**Step 5:** `git commit -m "feat: add reaction bar to comments and notification bell to navbar"`

---

## Wave 3 — Export I (Tasks 13-17)

### Task 13: ExportTemplateController + views + routes

**Files:**
- Create: `app/Domain/Export/Controllers/ExportTemplateController.php`
- Create: `app/Domain/Export/Requests/StoreExportTemplateRequest.php`
- Create: `resources/views/settings/export-templates/index.blade.php`
- Create: `resources/views/settings/export-templates/create.blade.php`
- Create: `resources/views/settings/export-templates/edit.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Export/ExportTemplateControllerTest.php`

**Step 1: Failing test**
```php
it('can create an export template', function (): void {
    $this->actingAs($user)
        ->post(route('settings.export-templates.store'), [
            'name' => 'Corporate Template',
            'primary_color' => '#003366',
            'is_default' => true,
        ])
        ->assertRedirect();

    expect(ExportTemplate::where('name', 'Corporate Template')->exists())->toBeTrue();
});
```

**Step 3: Controller** — standard resourceful controller with `index`, `create`, `store`, `edit`, `update`, `destroy`. In `store`/`update`: if `is_default = true`, set all other org templates to `is_default = false` first.

**Routes:**
```php
Route::resource('settings/export-templates', ExportTemplateController::class)
    ->names('settings.export-templates');
```

**Step 5:** `git commit -m "feat: add ExportTemplateController and settings views"`

---

### Task 14: Extend ExportController to save MomExport history

**Files:**
- Modify: `app/Domain/Export/Controllers/ExportController.php`
- Modify: `app/Domain/Export/Services/PdfExportService.php`
- Test: `tests/Feature/Domain/Export/ExportHistoryTest.php`

**Step 1: Failing test**
```php
it('saves export record when PDF is downloaded', function (): void {
    $this->actingAs($user)->get(route('export.pdf', $meeting));

    expect(MomExport::where('minutes_of_meeting_id', $meeting->id)->where('format', 'pdf')->exists())->toBeTrue();
});
```

**Step 3:** In each export method (pdf/word/csv), after the export service call, add:
```php
MomExport::create([
    'minutes_of_meeting_id' => $meeting->id,
    'user_id' => auth()->id(),
    'format' => 'pdf', // or 'docx', 'csv'
]);
```

Also check if org has a default `ExportTemplate` and pass it to `PdfExportService::export($meeting, $template)`.

**Step 5:** `git commit -m "feat: save export history on PDF/DOCX/CSV download"`

---

### Task 15: EmailDistributionController + SendMomEmailJob + MomDistributionMail

**Files:**
- Create: `app/Domain/Export/Controllers/EmailDistributionController.php`
- Create: `app/Domain/Export/Requests/SendEmailDistributionRequest.php`
- Create: `app/Domain/Export/Jobs/SendMomEmailJob.php`
- Create: `app/Domain/Export/Mail/MomDistributionMail.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Export/EmailDistributionTest.php`

**Step 1: Failing test**
```php
it('queues email distribution job', function (): void {
    Queue::fake();

    $this->actingAs($user)
        ->post(route('meetings.email-distribution.store', $meeting), [
            'recipients' => ['test@example.com', 'another@example.com'],
            'subject' => 'Meeting Minutes',
            'export_format' => 'pdf',
        ])
        ->assertRedirect();

    Queue::assertPushed(SendMomEmailJob::class);
    expect(MomEmailDistribution::count())->toBe(1);
});
```

**Step 3: Controller**
```php
public function store(SendEmailDistributionRequest $request, MinutesOfMeeting $meeting): RedirectResponse
{
    $this->authorize('view', $meeting);
    $dist = MomEmailDistribution::create([
        'minutes_of_meeting_id' => $meeting->id,
        'sent_by' => $request->user()->id,
        'recipients' => $request->validated('recipients'),
        'subject' => $request->validated('subject'),
        'body_note' => $request->validated('body_note'),
        'export_format' => $request->validated('export_format'),
        'status' => 'pending',
    ]);
    SendMomEmailJob::dispatch($dist);
    return redirect()->route('meetings.show', $meeting)->with('success', 'Email queued for delivery.');
}
```

**Step 3: SendMomEmailJob** — generates export, creates temp file, sends via `Mail::to($recipients)->send(new MomDistributionMail($meeting, $attachment))`, updates distribution status to `sent` or `failed`.

**Route:** `Route::post('meetings/{meeting}/email-distribution', [EmailDistributionController::class, 'store'])->name('meetings.email-distribution.store');`

**Step 5:** `git commit -m "feat: add email distribution for MOM with queued job"`

---

### Task 16: Email distribution modal in meeting show page

**Files:**
- Modify: `resources/views/meetings/show.blade.php` — add "Email MOM" button + Alpine.js modal

**Modal contents:**
- Recipients textarea (comma-separated emails, pre-filled from attendees)
- Subject input (default: "Minutes of Meeting — {{ $meeting->title }}")
- Optional note textarea
- Format selector (PDF / DOCX)
- Send button

**Step 5:** `git commit -m "feat: add email distribution modal to meeting show page"`

---

### Task 17: Export history tab in meeting show page

**Files:**
- Modify: `resources/views/meetings/show.blade.php` — add exports section or tab
- Create: `resources/views/meetings/partials/export-history.blade.php`

Show list of exports: format badge, who exported, when. Load via `$meeting->exports()->with('user')->latest()->get()`. Add `exports()` HasMany to `MinutesOfMeeting` model.

**Step 5:** `git commit -m "feat: add export history section to meeting show page"`

---

## Wave 4 — Analytics J (Tasks 18-21)

### Task 18: AnalyticsEventService + integrate into key controllers

**Files:**
- Create: `app/Domain/Analytics/Services/AnalyticsEventService.php`
- Modify: `app/Domain/Export/Controllers/ExportController.php`
- Modify: `app/Domain/Meeting/Controllers/MeetingController.php`
- Test: `tests/Feature/Domain/Analytics/AnalyticsEventServiceTest.php`

**Step 3: Service**
```php
<?php
declare(strict_types=1);
namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\AnalyticsEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AnalyticsEventService
{
    public static function track(
        string $eventType,
        Model $subject,
        ?User $user = null,
        array $properties = [],
    ): void {
        $orgId = method_exists($subject, 'getAttribute')
            ? ($subject->organization_id ?? $user?->current_organization_id)
            : $user?->current_organization_id;

        if (! $orgId) { return; }

        AnalyticsEvent::create([
            'organization_id' => $orgId,
            'user_id' => $user?->id,
            'event_type' => $eventType,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'properties' => $properties ?: null,
            'occurred_at' => now(),
        ]);
    }
}
```

Track `meeting.viewed` in `MeetingController::show()` and `export.downloaded` in `ExportController` methods.

**Step 5:** `git commit -m "feat: add AnalyticsEventService and integrate into controllers"`

---

### Task 19: GenerateDailyAnalyticsSnapshotJob + scheduler

**Files:**
- Create: `app/Domain/Analytics/Jobs/GenerateDailyAnalyticsSnapshotJob.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Domain/Analytics/GenerateDailyAnalyticsSnapshotJobTest.php`

**Step 3: Job**
```php
public function handle(): void
{
    $date = today()->subDay(); // snapshot for yesterday
    $orgIds = Organization::query()->pluck('id');

    foreach ($orgIds as $orgId) {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        AnalyticsDailySnapshot::updateOrCreate(
            ['organization_id' => $orgId, 'snapshot_date' => $date->toDateString()],
            [
                'total_meetings' => MinutesOfMeeting::where('organization_id', $orgId)->whereDate('meeting_date', $date)->count(),
                'total_action_items' => ActionItem::where('organization_id', $orgId)->whereBetween('created_at', [$start, $end])->count(),
                'completed_action_items' => ActionItem::where('organization_id', $orgId)->where('status', ActionItemStatus::Completed)->whereBetween('updated_at', [$start, $end])->count(),
                'overdue_action_items' => ActionItem::where('organization_id', $orgId)->where('due_date', '<', now())->whereNotIn('status', [ActionItemStatus::Completed, ActionItemStatus::Cancelled])->count(),
                'ai_usage_count' => AnalyticsEvent::where('organization_id', $orgId)->where('event_type', 'like', 'ai.%')->whereBetween('occurred_at', [$start, $end])->count(),
            ]
        );
    }
}
```

**Scheduler in `routes/console.php`:**
```php
Schedule::job(GenerateDailyAnalyticsSnapshotJob::class)->dailyAt('01:00');
```

**Step 5:** `git commit -m "feat: add GenerateDailyAnalyticsSnapshotJob and scheduler"`

---

### Task 20: AuditLogController + audit-log view

**Files:**
- Create: `app/Domain/Account/Controllers/AuditLogController.php`
- Create: `resources/views/audit-log/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Account/AuditLogControllerTest.php`

**Step 3: Controller**
```php
public function index(Request $request): View
{
    $query = AuditLog::query()
        ->where('organization_id', $request->user()->current_organization_id)
        ->with('user')
        ->latest();

    if ($request->filled('action')) {
        $query->where('action', $request->input('action'));
    }
    if ($request->filled('from')) {
        $query->whereDate('created_at', '>=', $request->input('from'));
    }
    if ($request->filled('to')) {
        $query->whereDate('created_at', '<=', $request->input('to'));
    }

    $logs = $query->paginate(50);
    return view('audit-log.index', compact('logs'));
}
```

**Route:** `Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit-log.index');`

**Step 5:** `git commit -m "feat: add AuditLogController and audit log view"`

---

### Task 21: Extend AnalyticsService to use snapshots for trend data

**Files:**
- Modify: `app/Domain/Analytics/Services/AnalyticsService.php`

Replace the heavy `getMeetingStats` raw queries for trend charts with a read from `AnalyticsDailySnapshot`. Keep real-time widgets as-is.

Add new method:
```php
public function getTrendData(int $orgId, int $days = 30): array
{
    return AnalyticsDailySnapshot::query()
        ->where('organization_id', $orgId)
        ->where('snapshot_date', '>=', now()->subDays($days)->toDateString())
        ->orderBy('snapshot_date')
        ->get(['snapshot_date', 'total_meetings', 'total_action_items', 'completed_action_items'])
        ->toArray();
}
```

**Step 5:** `git commit -m "feat: extend AnalyticsService with snapshot-based trend data"`

---

## Wave 5 — Settings (Tasks 22-27)

### Task 22: ProfileSettingsController + UserSettings model + view

**Files:**
- Create: `app/Domain/Account/Controllers/ProfileSettingsController.php`
- Create: `app/Domain/Account/Requests/UpdateProfileRequest.php`
- Create: `resources/views/settings/profile.blade.php`
- Test: `tests/Feature/Domain/Account/ProfileSettingsTest.php`

**Step 1: Failing test**
```php
it('can update profile name and timezone', function (): void {
    $this->actingAs($user)
        ->put(route('settings.profile.update'), ['name' => 'New Name', 'timezone' => 'Asia/Kuala_Lumpur'])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('New Name');
    expect(UserSettings::where('user_id', $user->id)->value('timezone'))->toBe('Asia/Kuala_Lumpur');
});
```

**Step 3: Controller**
```php
public function update(UpdateProfileRequest $request): RedirectResponse
{
    $user = $request->user();
    $user->update(['name' => $request->validated('name')]);

    UserSettings::updateOrCreate(
        ['user_id' => $user->id],
        ['timezone' => $request->validated('timezone', 'UTC'), 'locale' => $request->validated('locale', 'en')]
    );

    if ($request->hasFile('avatar')) {
        // store avatar, update user avatar_path
    }

    return redirect()->route('settings.profile')->with('success', 'Profile updated.');
}
```

**Route:** `Route::get/put('settings/profile', [ProfileSettingsController::class, 'edit/update'])->name('settings.profile/settings.profile.update');`

**Step 5:** `git commit -m "feat: add ProfileSettingsController with timezone and avatar support"`

---

### Task 23: NotificationSettingsController + view

**Files:**
- Create: `app/Domain/Account/Controllers/NotificationSettingsController.php`
- Create: `app/Domain/Account/Requests/UpdateNotificationSettingsRequest.php`
- Create: `resources/views/settings/notifications.blade.php`

**Step 3:** Save JSON preferences to `user_settings.notification_preferences`. View shows toggle switches per event type (mention_in_comment, action_item_assigned, meeting_finalized, action_item_overdue) × channel (email, in_app).

**Step 5:** `git commit -m "feat: add NotificationSettingsController with per-event preferences"`

---

### Task 24: SecuritySettingsController (password change + active sessions)

**Files:**
- Create: `app/Domain/Account/Controllers/SecuritySettingsController.php`
- Create: `app/Domain/Account/Requests/UpdatePasswordRequest.php`
- Create: `resources/views/settings/security.blade.php`

**Step 3:** Password change uses `Hash::check` + `Hash::make`. Active sessions list from `personal_access_tokens` table or `sessions` table if database driver used. Show IP, user agent, last used, with "Revoke" button.

**Step 5:** `git commit -m "feat: add SecuritySettingsController with password change and sessions"`

---

### Task 25: IntegrationSettingsController + view

**Files:**
- Create: `app/Domain/Account/Controllers/IntegrationSettingsController.php`
- Create: `resources/views/settings/integrations.blade.php`

**Step 3:** Read-only status page. Show Google Calendar connection status (from `calendar_connections` table), Teams webhook URL status (from `organizations.teams_webhook_url`). Link to connect/disconnect each integration. No new connections for now.

**Step 5:** `git commit -m "feat: add IntegrationSettingsController with calendar and Teams status"`

---

### Task 26: API Keys settings page (list/generate/revoke)

**Files:**
- Create: `app/Domain/Account/Controllers/ApiKeySettingsController.php`
- Create: `app/Domain/Account/Requests/CreateApiKeyRequest.php`
- Create: `resources/views/settings/api-keys.blade.php`
- Test: `tests/Feature/Domain/Account/ApiKeySettingsTest.php`

**Step 1: Failing test**
```php
it('can generate a new API key', function (): void {
    $this->actingAs($user)
        ->post(route('settings.api-keys.store'), ['name' => 'My Key'])
        ->assertRedirect();

    expect(ApiKey::where('organization_id', $org->id)->count())->toBe(1);
});

it('can revoke an API key', function (): void {
    $key = ApiKey::factory()->create(['organization_id' => $org->id]);
    $this->actingAs($user)->delete(route('settings.api-keys.destroy', $key))->assertRedirect();
    expect(ApiKey::find($key->id))->toBeNull();
});
```

**Step 3: Controller**
- `index` — list org's API keys, mask token (show first 8 chars + `***`)
- `store` — generate `Str::random(40)` token, hash with `hash('sha256', $token)`, show plain token ONCE in flash session
- `destroy` — delete key

**Step 5:** `git commit -m "feat: add API key management settings page"`

---

### Task 27: Settings sidebar navigation + all settings routes

**Files:**
- Modify: `resources/views/layouts/partials/sidebar.blade.php`
- Modify: `routes/web.php` — ensure all settings routes are registered

Add settings group in sidebar:
- Profile (`settings.profile`)
- Notifications (`settings.notifications`)
- Security (`settings.security`)
- Integrations (`settings.integrations`)
- API Keys (`settings.api-keys`)
- Export Templates (`settings.export-templates.index`)

**Step 5:** `git commit -m "feat: add settings navigation to sidebar and wire all settings routes"`

---

## Wave 6 — API Full Coverage (Tasks 28-35)

### Task 28: AttendeeApiController + AttendeeResource

**Files:**
- Create: `app/Domain/API/Controllers/V1/AttendeeApiController.php`
- Create: `app/Domain/API/Resources/AttendeeResource.php`
- Create: `app/Domain/API/Requests/V1/StoreApiAttendeeRequest.php`
- Test: `tests/Feature/Domain/API/AttendeeApiTest.php`

**Step 3: Controller** — extends `ApiController`, explicitly scopes all queries via `->whereHas('meeting', fn($q) => $q->where('organization_id', $this->organizationId($request)))`. Methods: `index`, `store`, `update`, `destroy`.

**Step 5:** `git commit -m "feat: add AttendeeApiController for V1 API"`

---

### Task 29: TranscriptionApiController + TranscriptionResource

**Files:**
- Create: `app/Domain/API/Controllers/V1/TranscriptionApiController.php`
- Create: `app/Domain/API/Resources/TranscriptionResource.php`
- Test: `tests/Feature/Domain/API/TranscriptionApiTest.php`

**Step 3:** Read-only endpoints. `index` returns list of transcriptions for a meeting. `show` returns full transcription with segments.

**Step 5:** `git commit -m "feat: add TranscriptionApiController (read-only) for V1 API"`

---

### Task 30: CommentApiController + CommentResource

**Files:**
- Create: `app/Domain/API/Controllers/V1/CommentApiController.php`
- Create: `app/Domain/API/Resources/CommentResource.php`
- Create: `app/Domain/API/Requests/V1/StoreApiCommentRequest.php`
- Test: `tests/Feature/Domain/API/CommentApiTest.php`

**Step 3:** CRUD for comments on meetings. Must explicitly scope with `->where('organization_id', ...)` since global scopes inactive in API.

**Step 5:** `git commit -m "feat: add CommentApiController for V1 API"`

---

### Task 31: AnalyticsApiController + AnalyticsSummaryResource

**Files:**
- Create: `app/Domain/API/Controllers/V1/AnalyticsApiController.php`
- Create: `app/Domain/API/Resources/AnalyticsSummaryResource.php`
- Test: `tests/Feature/Domain/API/AnalyticsApiTest.php`

**Step 3:** `summary` method returns last 30-day snapshot aggregates. Use `AnalyticsDailySnapshot` or fall back to `AnalyticsService` if no snapshots exist yet.

**Step 5:** `git commit -m "feat: add AnalyticsApiController for V1 API"`

---

### Task 32: WebhookApiController

**Files:**
- Create: `app/Domain/API/Controllers/V1/WebhookApiController.php`
- Create: `app/Domain/API/Requests/V1/StoreApiWebhookRequest.php`
- Test: `tests/Feature/Domain/API/WebhookApiTest.php`

**Step 3:** `index`, `store` (create webhook endpoint with random `secret`), `destroy`. Uses `WebhookEndpoint` model.

**Step 5:** `git commit -m "feat: add WebhookApiController for V1 API"`

---

### Task 33: ApiInfoController (GET /api/v1 — public, no auth)

**Files:**
- Create: `app/Domain/API/Controllers/V1/ApiInfoController.php`
- Test: `tests/Feature/Domain/API/ApiInfoTest.php`

**Step 3:** Returns JSON with `name`, `version: 'v1'`, `endpoints` array listing all available routes. No auth required.

**Route:** Registered OUTSIDE the `ApiKeyAuthentication` middleware group.

**Step 5:** `git commit -m "feat: add ApiInfoController at GET /api/v1"`

---

### Task 34: Tier-based API rate limiting

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Domain/API/RateLimitingTest.php`

**Step 3:** Update `RateLimiter::for('api', ...)` to check if org has active Pro subscription → 300 req/min, else 60 req/min.

**Step 5:** `git commit -m "feat: implement tier-based API rate limiting (Free 60/min, Pro 300/min)"`

---

### Task 35: Final routes/api.php wiring + integration test

**Files:**
- Modify: `routes/api.php` — add all new V1 routes
- Create: `tests/Feature/Domain/API/V1IntegrationTest.php`

**Step 3: Final routes/api.php**
Wire all controllers from Tasks 28-33. Register `GET /api/v1` (public, no auth) before the middleware group.

**Step 4:** Run full test suite: `php artisan test --compact`

**Step 5:** `git commit -m "feat: wire all V1 API routes and add integration tests"`

---

## Sidebar Menu Updates (Wave 5 — Task 27)

Add to sidebar under new "Settings" section:
- Profile
- Notifications
- Security
- Integrations
- API Keys
- Export Templates

Also add under existing sections:
- Analytics → Audit Log
- (Collaboration features accessed from meeting pages, not sidebar)
