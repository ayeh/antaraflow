# antaraFLOW Phase 1 — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build the complete Phase 1 MVP of antaraFLOW — an AI-powered Minutes of Meeting platform with multi-tenancy, AI extraction, transcription, action items, and attendee management.

**Architecture:** Domain-driven monolith in Laravel 12. Code is organized under `app/Domain/` by business domain (Meeting, Transcription, AI, etc.), with shared infrastructure in `app/Infrastructure/` and cross-cutting concerns in `app/Support/`. Multi-tenancy via `organization_id` foreign keys + global scopes (no separate databases).

**Tech Stack:** Laravel 12, PHP 8.4, MySQL/SQLite, Pest v4, Tailwind CSS v4, Alpine.js, Vanilla JS ES6+, Vite

---

## Migration Dependency Order (Reference)

This is the required migration execution order based on foreign key dependencies:

```
Layer 0 (no deps):     organizations, subscription_plans, mom_tags
Layer 1 (→ orgs):      users (modify), api_keys, ai_provider_configs, attendee_groups, export_templates
Layer 2 (→ users+orgs): organization_subscriptions, usage_trackings, audit_logs, meeting_series, meeting_templates
Layer 3 (→ MOM):       minutes_of_meetings
Layer 4 (→ MOM+):      mom_versions, mom_tag_mom (pivot), audio_transcriptions, mom_inputs, mom_manual_notes,
                        mom_extractions, mom_topics, mom_ai_conversations, action_items, mom_attendees,
                        mom_join_settings, mom_comments, mom_guest_accesses, mom_exports, mom_email_distributions
Layer 5 (→ Layer 4):   transcription_segments, action_item_histories, mom_mentions, mom_reactions
Layer 6 (→ users):     notifications
```

---

## Week 1 — Foundation & Infrastructure

### Task 1.1: Domain Directory Structure

**Files:**
- Create: `app/Domain/` (directory tree)
- Create: `app/Infrastructure/` (directory tree)
- Create: `app/Support/` (directory tree)

**Step 1: Create the domain directory structure**

```bash
mkdir -p app/Domain/{Meeting,Transcription,AI,ActionItem,Attendee,Collaboration,Analytics,Export,Account}/{Models,Services,Policies,Requests,Controllers,Events,Listeners,Jobs,Notifications}
mkdir -p app/Infrastructure/{AI,Storage,Tenancy}
mkdir -p app/Support/{Enums,Helpers,Traits}
```

**Step 2: Register PSR-4 autoload namespaces in composer.json**

Add to `composer.json` under `autoload.psr-4`:

```json
"App\\Domain\\": "app/Domain/",
"App\\Infrastructure\\": "app/Infrastructure/",
"App\\Support\\": "app/Support/"
```

Run: `composer dump-autoload`

**Step 3: Commit**

```bash
git add -A && git commit -m "feat: scaffold domain directory structure"
```

---

### Task 1.2: Support Enums

**Files:**
- Create: `app/Support/Enums/MeetingStatus.php`
- Create: `app/Support/Enums/UserRole.php`
- Create: `app/Support/Enums/ActionItemPriority.php`
- Create: `app/Support/Enums/ActionItemStatus.php`
- Create: `app/Support/Enums/TranscriptionStatus.php`
- Create: `app/Support/Enums/InputType.php`
- Create: `app/Support/Enums/AttendeeRole.php`
- Create: `app/Support/Enums/RsvpStatus.php`
- Create: `app/Support/Enums/ExportFormat.php`
- Test: `tests/Unit/Support/Enums/`

**Step 1: Write tests for enums**

Create a test file per enum that verifies cases exist and values are correct. Example pattern:

```php
// tests/Unit/Support/Enums/MeetingStatusTest.php
test('meeting status has expected cases', function () {
    expect(MeetingStatus::cases())->toHaveCount(4);
    expect(MeetingStatus::Draft->value)->toBe('draft');
    expect(MeetingStatus::InProgress->value)->toBe('in_progress');
    expect(MeetingStatus::Finalized->value)->toBe('finalized');
    expect(MeetingStatus::Approved->value)->toBe('approved');
});
```

**Step 2: Run tests — verify they fail**

```bash
php artisan test --compact --filter=Enums
```

**Step 3: Implement all enums**

```php
// app/Support/Enums/MeetingStatus.php
<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum MeetingStatus: string
{
    case Draft = 'draft';
    case InProgress = 'in_progress';
    case Finalized = 'finalized';
    case Approved = 'approved';
}
```

Create similar enums for:

- `UserRole`: Owner, Admin, Manager, Member, Viewer
- `ActionItemPriority`: Low, Medium, High, Critical
- `ActionItemStatus`: Open, InProgress, Completed, Cancelled, CarriedForward
- `TranscriptionStatus`: Pending, Processing, Completed, Failed
- `InputType`: Audio, Document, ManualNote, BrowserRecording
- `AttendeeRole`: Organizer, Presenter, NoteTaker, Participant, Observer
- `RsvpStatus`: Pending, Accepted, Declined, Tentative
- `ExportFormat`: Pdf, Docx, Json, Markdown

**Step 4: Run tests — verify they pass**

```bash
php artisan test --compact --filter=Enums
```

**Step 5: Run Pint**

```bash
vendor/bin/pint --dirty --format agent
```

**Step 6: Commit**

```bash
git add -A && git commit -m "feat: add support enums for all domain value types"
```

---

### Task 1.3: Multi-Tenancy Infrastructure

**Files:**
- Create: `app/Support/Traits/BelongsToOrganization.php`
- Create: `app/Infrastructure/Tenancy/OrganizationScope.php`
- Create: `app/Infrastructure/Tenancy/SetOrganizationContext.php` (middleware)
- Test: `tests/Unit/Infrastructure/Tenancy/OrganizationScopeTest.php`

**Step 1: Write failing test for BelongsToOrganization trait**

```php
// tests/Unit/Infrastructure/Tenancy/OrganizationScopeTest.php
use App\Support\Traits\BelongsToOrganization;
use App\Infrastructure\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Model;

test('BelongsToOrganization trait boots global scope', function () {
    $model = new class extends Model {
        use BelongsToOrganization;
        protected $table = 'test_models';
    };

    // Verify the trait adds the organization relationship method
    expect(method_exists($model, 'organization'))->toBeTrue();
});
```

**Step 2: Run test — verify failure**

```bash
php artisan test --compact --filter=OrganizationScope
```

**Step 3: Implement BelongsToOrganization trait**

```php
// app/Support/Traits/BelongsToOrganization.php
<?php

declare(strict_types=1);

namespace App\Support\Traits;

use App\Domain\Account\Models\Organization;
use App\Infrastructure\Tenancy\OrganizationScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function ($model) {
            if (! $model->organization_id && auth()->check()) {
                $model->organization_id = auth()->user()->current_organization_id;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
```

**Step 4: Implement OrganizationScope**

```php
// app/Infrastructure/Tenancy/OrganizationScope.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class OrganizationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check() && auth()->user()->current_organization_id) {
            $builder->where(
                $model->getTable().'.organization_id',
                auth()->user()->current_organization_id
            );
        }
    }
}
```

**Step 5: Implement SetOrganizationContext middleware**

```php
// app/Infrastructure/Tenancy/SetOrganizationContext.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetOrganizationContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->current_organization_id) {
            $firstOrg = $request->user()->organizations()->first();
            if ($firstOrg) {
                $request->user()->update(['current_organization_id' => $firstOrg->id]);
            }
        }

        return $next($request);
    }
}
```

**Step 6: Run tests — verify pass**

```bash
php artisan test --compact --filter=OrganizationScope
```

**Step 7: Run Pint, commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: add multi-tenancy infrastructure (trait, scope, middleware)"
```

---

### Task 1.4: AI Provider Contracts & Factory

**Files:**
- Create: `app/Infrastructure/AI/Contracts/AIProviderInterface.php`
- Create: `app/Infrastructure/AI/Contracts/TranscriberInterface.php`
- Create: `app/Infrastructure/AI/DTOs/MeetingSummary.php`
- Create: `app/Infrastructure/AI/DTOs/TranscriptionResult.php`
- Create: `app/Infrastructure/AI/DTOs/ExtractedActionItem.php`
- Create: `app/Infrastructure/AI/DTOs/ExtractedDecision.php`
- Create: `app/Infrastructure/AI/AIProviderFactory.php`
- Create: `app/Infrastructure/AI/Providers/OpenAIProvider.php` (stub)
- Create: `app/Infrastructure/AI/Providers/AnthropicProvider.php` (stub)
- Create: `app/Infrastructure/AI/Providers/GoogleProvider.php` (stub)
- Create: `app/Infrastructure/AI/Providers/OllamaProvider.php` (stub)
- Test: `tests/Unit/Infrastructure/AI/AIProviderFactoryTest.php`

**Step 1: Write failing test for AIProviderFactory**

```php
// tests/Unit/Infrastructure/AI/AIProviderFactoryTest.php
use App\Infrastructure\AI\AIProviderFactory;
use App\Infrastructure\AI\Contracts\AIProviderInterface;

test('factory creates openai provider', function () {
    $provider = AIProviderFactory::make('openai', [
        'api_key' => 'test-key',
        'model' => 'gpt-4o',
    ]);

    expect($provider)->toBeInstanceOf(AIProviderInterface::class);
});

test('factory throws exception for unknown provider', function () {
    AIProviderFactory::make('unknown', []);
})->throws(\InvalidArgumentException::class);
```

**Step 2: Run test — verify failure**

```bash
php artisan test --compact --filter=AIProviderFactory
```

**Step 3: Create DTOs**

```php
// app/Infrastructure/AI/DTOs/MeetingSummary.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\DTOs;

final readonly class MeetingSummary
{
    public function __construct(
        public string $summary,
        public string $keyPoints,
        public float $confidenceScore,
    ) {}
}
```

Create similar DTOs for `TranscriptionResult`, `ExtractedActionItem`, `ExtractedDecision`.

**Step 4: Create interfaces**

```php
// app/Infrastructure/AI/Contracts/AIProviderInterface.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Contracts;

use App\Infrastructure\AI\DTOs\ExtractedActionItem;
use App\Infrastructure\AI\DTOs\ExtractedDecision;
use App\Infrastructure\AI\DTOs\MeetingSummary;

interface AIProviderInterface
{
    public function chat(string $prompt, array $context = []): string;

    public function summarize(string $text): MeetingSummary;

    /** @return array<ExtractedActionItem> */
    public function extractActionItems(string $text): array;

    /** @return array<ExtractedDecision> */
    public function extractDecisions(string $text): array;
}
```

```php
// app/Infrastructure/AI/Contracts/TranscriberInterface.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Contracts;

use App\Infrastructure\AI\DTOs\TranscriptionResult;

interface TranscriberInterface
{
    public function transcribe(string $filePath, array $options = []): TranscriptionResult;

    public function supportsDiarization(): bool;

    /** @return array<string> */
    public function supportedLanguages(): array;
}
```

**Step 5: Create stub providers**

Each provider implements `AIProviderInterface` with basic structure. Full implementation comes in Week 5. Example:

```php
// app/Infrastructure/AI/Providers/OpenAIProvider.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Providers;

use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\DTOs\ExtractedActionItem;
use App\Infrastructure\AI\DTOs\ExtractedDecision;
use App\Infrastructure\AI\DTOs\MeetingSummary;

class OpenAIProvider implements AIProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o',
    ) {}

    public function chat(string $prompt, array $context = []): string
    {
        throw new \RuntimeException('OpenAI provider not yet implemented.');
    }

    public function summarize(string $text): MeetingSummary
    {
        throw new \RuntimeException('OpenAI provider not yet implemented.');
    }

    /** @return array<ExtractedActionItem> */
    public function extractActionItems(string $text): array
    {
        throw new \RuntimeException('OpenAI provider not yet implemented.');
    }

    /** @return array<ExtractedDecision> */
    public function extractDecisions(string $text): array
    {
        throw new \RuntimeException('OpenAI provider not yet implemented.');
    }
}
```

Create identical stubs for `AnthropicProvider`, `GoogleProvider`, `OllamaProvider`.

**Step 6: Create AIProviderFactory**

```php
// app/Infrastructure/AI/AIProviderFactory.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\Providers\AnthropicProvider;
use App\Infrastructure\AI\Providers\GoogleProvider;
use App\Infrastructure\AI\Providers\OllamaProvider;
use App\Infrastructure\AI\Providers\OpenAIProvider;
use InvalidArgumentException;

class AIProviderFactory
{
    /** @param array<string, mixed> $config */
    public static function make(string $provider, array $config): AIProviderInterface
    {
        return match ($provider) {
            'openai' => new OpenAIProvider(
                apiKey: $config['api_key'] ?? '',
                model: $config['model'] ?? 'gpt-4o',
            ),
            'anthropic' => new AnthropicProvider(
                apiKey: $config['api_key'] ?? '',
                model: $config['model'] ?? 'claude-sonnet-4-20250514',
            ),
            'google' => new GoogleProvider(
                apiKey: $config['api_key'] ?? '',
                model: $config['model'] ?? 'gemini-2.0-flash',
            ),
            'ollama' => new OllamaProvider(
                baseUrl: $config['base_url'] ?? 'http://localhost:11434',
                model: $config['model'] ?? 'llama3.2',
            ),
            default => throw new InvalidArgumentException("Unknown AI provider: {$provider}"),
        };
    }
}
```

**Step 7: Run tests — verify pass**

```bash
php artisan test --compact --filter=AIProviderFactory
```

**Step 8: Run Pint, commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: add AI provider contracts, DTOs, factory, and stub providers"
```

---

### Task 1.5: Foundation Migrations (Layer 0-1)

**Files:**
- Create: `database/migrations/2026_03_01_000001_create_organizations_table.php`
- Create: `database/migrations/2026_03_01_000002_create_subscription_plans_table.php`
- Create: `database/migrations/2026_03_01_000003_add_organization_fields_to_users_table.php`
- Create: `database/migrations/2026_03_01_000004_create_api_keys_table.php`
- Create: `database/migrations/2026_03_01_000005_create_ai_provider_configs_table.php`
- Create: `database/migrations/2026_03_01_000006_create_mom_tags_table.php`

**Step 1: Create organizations migration**

```bash
php artisan make:migration create_organizations_table --no-interaction
```

```php
Schema::create('organizations', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('logo_path')->nullable();
    $table->json('settings')->nullable();
    $table->string('timezone')->default('UTC');
    $table->string('language')->default('en');
    $table->timestamps();
    $table->softDeletes();
});
```

**Step 2: Create subscription_plans migration**

```php
Schema::create('subscription_plans', function (Blueprint $table) {
    $table->id();
    $table->string('name');           // Free, Pro, Business, Enterprise
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->decimal('price_monthly', 10, 2)->default(0);
    $table->decimal('price_yearly', 10, 2)->default(0);
    $table->json('features');         // feature flags & limits
    $table->integer('max_users')->default(1);
    $table->integer('max_meetings_per_month')->default(10);
    $table->integer('max_audio_minutes_per_month')->default(60);
    $table->integer('max_storage_mb')->default(500);
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**Step 3: Add organization fields to users table**

```php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('current_organization_id')->nullable()->constrained('organizations')->nullOnDelete();
    $table->string('phone')->nullable()->after('email');
    $table->string('avatar_path')->nullable();
    $table->string('timezone')->default('UTC');
    $table->string('language')->default('en');
    $table->json('preferences')->nullable();
    $table->timestamp('last_login_at')->nullable();
    $table->softDeletes();
});
```

**Step 4: Create api_keys migration**

```php
Schema::create('api_keys', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('key', 64)->unique();
    $table->string('secret_hash');
    $table->json('permissions')->nullable();
    $table->timestamp('last_used_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Step 5: Create ai_provider_configs migration**

```php
Schema::create('ai_provider_configs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('provider');        // openai, anthropic, google, ollama
    $table->string('display_name');
    $table->text('api_key_encrypted')->nullable();
    $table->string('model')->nullable();
    $table->string('base_url')->nullable();
    $table->json('settings')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['organization_id', 'provider', 'model']);
});
```

**Step 6: Create mom_tags migration**

```php
Schema::create('mom_tags', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->string('slug');
    $table->string('color', 7)->default('#6366f1');
    $table->timestamps();

    $table->unique(['organization_id', 'slug']);
});
```

**Step 7: Run migrations**

```bash
php artisan migrate --no-interaction
```

**Step 8: Run Pint, commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: add foundation migrations (organizations, plans, users, api_keys, ai_configs, tags)"
```

---

### Task 1.6: Organization & User Models

**Files:**
- Create: `app/Domain/Account/Models/Organization.php`
- Create: `app/Domain/Account/Models/SubscriptionPlan.php`
- Modify: `app/Models/User.php` (add organization relationship, role)
- Create: `database/factories/OrganizationFactory.php`
- Create: `database/factories/SubscriptionPlanFactory.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/Domain/Account/Models/OrganizationTest.php`

**Step 1: Write failing test**

```php
// tests/Feature/Domain/Account/Models/OrganizationTest.php
use App\Domain\Account\Models\Organization;
use App\Models\User;

test('organization has many users', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    expect($org->users)->toHaveCount(1);
    expect($org->users->first()->id)->toBe($user->id);
});

test('user belongs to current organization', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $org->id]);

    expect($user->currentOrganization->id)->toBe($org->id);
});
```

**Step 2: Run test — verify failure**

```bash
php artisan test --compact --filter=OrganizationTest
```

**Step 3: Create Organization model**

```php
// app/Domain/Account/Models/Organization.php
<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'current_organization_id');
    }
}
```

**Step 4: Create factories**

```php
// database/factories/OrganizationFactory.php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Account\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Organization> */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'timezone' => 'UTC',
            'language' => 'en',
        ];
    }
}
```

**Step 5: Update User model — add organization relationships**

Add to `app/Models/User.php`:

```php
use App\Domain\Account\Models\Organization;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// Add SoftDeletes trait
// Add relationship:
public function currentOrganization(): BelongsTo
{
    return $this->belongsTo(Organization::class, 'current_organization_id');
}

// Add casts method:
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'preferences' => 'array',
        'last_login_at' => 'datetime',
    ];
}
```

**Step 6: Update UserFactory to support organization_id**

Add a `withOrganization` state:

```php
public function withOrganization(?Organization $organization = null): static
{
    return $this->state(fn (array $attributes) => [
        'current_organization_id' => $organization?->id ?? Organization::factory(),
    ]);
}
```

**Step 7: Run tests — verify pass**

```bash
php artisan test --compact --filter=OrganizationTest
```

**Step 8: Run Pint, commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: add Organization model, factories, and user-organization relationship"
```

---

### Task 1.7: Organization-User Pivot (Roles & Membership)

**Files:**
- Create: `database/migrations/2026_03_01_000007_create_organization_user_table.php`
- Modify: `app/Domain/Account/Models/Organization.php` (add members relationship)
- Modify: `app/Models/User.php` (add organizations relationship)
- Test: `tests/Feature/Domain/Account/Models/OrganizationMembershipTest.php`

**Step 1: Write failing test**

```php
// tests/Feature/Domain/Account/Models/OrganizationMembershipTest.php
use App\Domain\Account\Models\Organization;
use App\Models\User;
use App\Support\Enums\UserRole;

test('user can belong to multiple organizations with roles', function () {
    $user = User::factory()->create();
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    $user->organizations()->attach($org1, ['role' => UserRole::Owner->value]);
    $user->organizations()->attach($org2, ['role' => UserRole::Member->value]);

    expect($user->organizations)->toHaveCount(2);
});

test('organization has members with roles', function () {
    $org = Organization::factory()->create();
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $org->members()->attach($owner, ['role' => UserRole::Owner->value]);
    $org->members()->attach($member, ['role' => UserRole::Member->value]);

    expect($org->members)->toHaveCount(2);
    expect($org->members->first()->pivot->role)->toBe(UserRole::Owner->value);
});
```

**Step 2: Run tests — verify failure**

**Step 3: Create pivot migration**

```php
Schema::create('organization_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role')->default('member');
    $table->timestamps();

    $table->unique(['organization_id', 'user_id']);
});
```

**Step 4: Add relationships to models**

Organization:
```php
public function members(): BelongsToMany
{
    return $this->belongsToMany(User::class)
        ->withPivot('role')
        ->withTimestamps();
}
```

User:
```php
public function organizations(): BelongsToMany
{
    return $this->belongsToMany(Organization::class)
        ->withPivot('role')
        ->withTimestamps();
}
```

**Step 5: Run migrations, tests — verify pass**

```bash
php artisan migrate --no-interaction
php artisan test --compact --filter=OrganizationMembership
```

**Step 6: Run Pint, commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: add organization-user pivot table with role membership"
```

---

## Week 2 — Account Module (Module K)

### Task 2.1: Account Layer 2 Migrations

**Files:**
- Create: `database/migrations/..._create_organization_subscriptions_table.php`
- Create: `database/migrations/..._create_usage_trackings_table.php`
- Create: `database/migrations/..._create_audit_logs_table.php`

**Step 1: Create organization_subscriptions migration**

```php
Schema::create('organization_subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
    $table->string('status')->default('active');   // active, cancelled, expired, trial
    $table->timestamp('trial_ends_at')->nullable();
    $table->timestamp('starts_at');
    $table->timestamp('ends_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
});
```

**Step 2: Create usage_trackings migration**

```php
Schema::create('usage_trackings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('metric');          // meetings_count, audio_minutes, storage_mb, ai_requests
    $table->decimal('value', 12, 2)->default(0);
    $table->string('period');          // 2026-03
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->unique(['organization_id', 'metric', 'period']);
});
```

**Step 3: Create audit_logs migration**

```php
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('action');          // created, updated, deleted, exported, shared
    $table->string('auditable_type');
    $table->unsignedBigInteger('auditable_id');
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->string('ip_address', 45)->nullable();
    $table->string('user_agent')->nullable();
    $table->timestamps();

    $table->index(['auditable_type', 'auditable_id']);
    $table->index(['organization_id', 'created_at']);
});
```

**Step 4: Run migrations**

```bash
php artisan migrate --no-interaction
```

**Step 5: Run Pint, commit**

```bash
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: add account module migrations (subscriptions, usage, audit)"
```

---

### Task 2.2: Account Models & Factories

**Files:**
- Create: `app/Domain/Account/Models/OrganizationSubscription.php`
- Create: `app/Domain/Account/Models/UsageTracking.php`
- Create: `app/Domain/Account/Models/AuditLog.php`
- Create: `app/Domain/Account/Models/ApiKey.php`
- Create: `app/Domain/Account/Models/AiProviderConfig.php`
- Create: factories for each
- Test: `tests/Feature/Domain/Account/Models/AccountModelsTest.php`

**Step 1: Write tests for model relationships**

Test that each model:
- Has correct `fillable`/`guarded` attributes
- Belongs to organization
- Has proper casts
- Factory creates valid instances

**Step 2: Create models with BelongsToOrganization trait**

Each model uses the `BelongsToOrganization` trait and has proper relationship methods.

**Step 3: Create factories**

Each factory generates valid test data.

**Step 4: Run tests, Pint, commit**

```bash
php artisan test --compact --filter=AccountModels
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: add account module models and factories"
```

---

### Task 2.3: Account Services (RBAC)

**Files:**
- Create: `app/Domain/Account/Services/AuthorizationService.php`
- Create: `app/Domain/Account/Services/OrganizationService.php`
- Create: `app/Domain/Account/Services/AuditService.php`
- Test: `tests/Feature/Domain/Account/Services/AuthorizationServiceTest.php`
- Test: `tests/Feature/Domain/Account/Services/OrganizationServiceTest.php`

**Step 1: Write tests for authorization checks**

```php
test('owner can manage organization', function () { ... });
test('admin can manage members', function () { ... });
test('member cannot manage organization', function () { ... });
test('viewer can only read', function () { ... });
```

**Step 2: Implement AuthorizationService**

Role hierarchy: Owner > Admin > Manager > Member > Viewer. Each role inherits permissions from below.

```php
class AuthorizationService
{
    public function hasPermission(User $user, Organization $organization, string $permission): bool
    {
        $membership = $user->organizations()->where('organization_id', $organization->id)->first();
        if (! $membership) {
            return false;
        }

        $role = UserRole::from($membership->pivot->role);

        return $this->roleHasPermission($role, $permission);
    }

    private function roleHasPermission(UserRole $role, string $permission): bool
    {
        $permissions = $this->getPermissionsForRole($role);

        return in_array($permission, $permissions);
    }
}
```

**Step 3: Implement OrganizationService**

Handles create org, invite user, remove user, change role, update settings.

**Step 4: Implement AuditService**

```php
class AuditService
{
    public function log(string $action, Model $auditable, ?array $oldValues = null, ?array $newValues = null): void
    {
        AuditLog::query()->create([
            'organization_id' => $auditable->organization_id ?? auth()->user()?->current_organization_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
```

**Step 5: Run tests, Pint, commit**

```bash
php artisan test --compact --filter=Account
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: add account services (authorization, organization, audit)"
```

---

### Task 2.4: Account Policies & Form Requests

**Files:**
- Create: `app/Domain/Account/Policies/OrganizationPolicy.php`
- Create: `app/Domain/Account/Requests/CreateOrganizationRequest.php`
- Create: `app/Domain/Account/Requests/UpdateOrganizationRequest.php`
- Create: `app/Domain/Account/Requests/InviteMemberRequest.php`
- Test: `tests/Feature/Domain/Account/Policies/OrganizationPolicyTest.php`

**Step 1: Write tests for policy**

```php
test('owner can update organization', function () { ... });
test('member cannot delete organization', function () { ... });
test('admin can invite members', function () { ... });
```

**Step 2: Implement policy and form requests**

Register policies in `AppServiceProvider`.

**Step 3: Run tests, Pint, commit**

---

### Task 2.5: Account Controllers & Routes

**Files:**
- Create: `app/Domain/Account/Controllers/OrganizationController.php`
- Create: `app/Domain/Account/Controllers/MemberController.php`
- Create: `app/Domain/Account/Controllers/OrganizationSettingsController.php`
- Modify: `routes/web.php` (add account routes)
- Modify: `bootstrap/app.php` (register tenancy middleware)
- Test: `tests/Feature/Domain/Account/Controllers/OrganizationControllerTest.php`

**Step 1: Write HTTP tests**

```php
test('authenticated user can create organization', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/organizations', [
        'name' => 'Test Corp',
        'slug' => 'test-corp',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('organizations', ['name' => 'Test Corp']);
});

test('guest cannot access organizations', function () {
    $this->get('/organizations')->assertRedirect('/login');
});
```

**Step 2: Implement controllers**

Controllers delegate to services. Thin controllers, fat services.

**Step 3: Add routes**

```php
// routes/web.php
Route::middleware(['auth', 'org.context'])->group(function () {
    Route::resource('organizations', OrganizationController::class);
    Route::resource('organizations.members', MemberController::class)->shallow();
    Route::get('organizations/{organization}/settings', [OrganizationSettingsController::class, 'edit'])->name('organizations.settings.edit');
    Route::put('organizations/{organization}/settings', [OrganizationSettingsController::class, 'update'])->name('organizations.settings.update');
});
```

**Step 4: Register middleware alias in bootstrap/app.php**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'org.context' => \App\Infrastructure\Tenancy\SetOrganizationContext::class,
    ]);
})
```

**Step 5: Run tests, Pint, commit**

```bash
php artisan test --compact
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: add account controllers, routes, and middleware registration"
```

---

### Task 2.6: Authentication Setup

**Files:**
- Create: `app/Domain/Account/Controllers/Auth/RegisterController.php`
- Create: `app/Domain/Account/Controllers/Auth/LoginController.php`
- Create: `app/Domain/Account/Controllers/Auth/LogoutController.php`
- Create: `app/Domain/Account/Requests/RegisterRequest.php`
- Create: `app/Domain/Account/Requests/LoginRequest.php`
- Create: Blade views for login/register (minimal)
- Test: `tests/Feature/Domain/Account/Controllers/Auth/AuthenticationTest.php`

**Step 1: Write auth tests**

```php
test('user can register with organization', function () { ... });
test('user can login', function () { ... });
test('user can logout', function () { ... });
test('registration creates default organization', function () { ... });
```

**Step 2: Implement auth flow**

Registration creates user + default organization + assigns Owner role.

**Step 3: Run tests, Pint, commit**

---

### Task 2.7: Seeder for Subscription Plans

**Files:**
- Create: `database/seeders/SubscriptionPlanSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Domain/Account/Seeders/SubscriptionPlanSeederTest.php`

**Step 1: Create seeder with 4 tiers**

Free, Pro ($19/mo), Business ($49/mo), Enterprise (custom).

Each tier defines: max_users, max_meetings_per_month, max_audio_minutes_per_month, max_storage_mb, features JSON.

**Step 2: Run seeder, test, commit**

---

## Week 3 — Core MOM Module (Module A)

### Task 3.1: Core MOM Migrations

**Files:**
- Create: `database/migrations/..._create_meeting_series_table.php`
- Create: `database/migrations/..._create_meeting_templates_table.php`
- Create: `database/migrations/..._create_minutes_of_meetings_table.php`
- Create: `database/migrations/..._create_mom_versions_table.php`
- Create: `database/migrations/..._create_mom_tag_mom_table.php` (pivot)

**Step 1: Create meeting_series migration**

```php
Schema::create('meeting_series', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('recurrence_pattern')->nullable();  // weekly, biweekly, monthly
    $table->json('recurrence_config')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Step 2: Create meeting_templates migration**

```php
Schema::create('meeting_templates', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->json('structure');         // template sections/structure
    $table->json('default_settings')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_shared')->default(true);
    $table->timestamps();
});
```

**Step 3: Create minutes_of_meetings migration**

```php
Schema::create('minutes_of_meetings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->foreignId('meeting_series_id')->nullable()->constrained()->nullOnDelete();
    $table->foreignId('meeting_template_id')->nullable()->constrained()->nullOnDelete();
    $table->string('title');
    $table->text('summary')->nullable();
    $table->longText('content')->nullable();
    $table->string('status')->default('draft');
    $table->string('location')->nullable();
    $table->timestamp('meeting_date')->nullable();
    $table->integer('duration_minutes')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['organization_id', 'status']);
    $table->index(['organization_id', 'meeting_date']);
    $table->fullText(['title', 'summary', 'content']);
});
```

**Step 4: Create mom_versions migration**

```php
Schema::create('mom_versions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->integer('version_number');
    $table->longText('content');
    $table->text('change_summary')->nullable();
    $table->json('snapshot');          // full snapshot of MOM at version time
    $table->timestamps();

    $table->unique(['minutes_of_meeting_id', 'version_number']);
});
```

**Step 5: Create mom_tag pivot migration**

```php
Schema::create('mom_tag_mom', function (Blueprint $table) {
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->foreignId('mom_tag_id')->constrained()->cascadeOnDelete();

    $table->primary(['minutes_of_meeting_id', 'mom_tag_id']);
});
```

**Step 6: Run migrations, Pint, commit**

```bash
php artisan migrate --no-interaction
vendor/bin/pint --dirty --format agent
git add -A && git commit -m "feat: add core MOM migrations (series, templates, meetings, versions, tags)"
```

---

### Task 3.2: Core MOM Models & Factories

**Files:**
- Create: `app/Domain/Meeting/Models/MinutesOfMeeting.php`
- Create: `app/Domain/Meeting/Models/MeetingSeries.php`
- Create: `app/Domain/Meeting/Models/MeetingTemplate.php`
- Create: `app/Domain/Meeting/Models/MomVersion.php`
- Create: `app/Domain/Meeting/Models/MomTag.php`
- Create: factories for each
- Test: `tests/Feature/Domain/Meeting/Models/MeetingModelsTest.php`

**Step 1: Write tests for model relationships**

```php
test('minutes of meeting belongs to organization', function () { ... });
test('minutes of meeting has many versions', function () { ... });
test('minutes of meeting belongs to series', function () { ... });
test('minutes of meeting has many tags', function () { ... });
test('meeting series has many meetings', function () { ... });
test('mom version belongs to meeting', function () { ... });
```

**Step 2: Create MinutesOfMeeting model**

```php
class MinutesOfMeeting extends Model
{
    use BelongsToOrganization, HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'datetime',
            'metadata' => 'array',
            'status' => MeetingStatus::class,
        ];
    }

    public function createdBy(): BelongsTo { ... }
    public function series(): BelongsTo { ... }
    public function template(): BelongsTo { ... }
    public function versions(): HasMany { ... }
    public function tags(): BelongsToMany { ... }
    public function latestVersion(): HasOne { ... }
}
```

**Step 3: Create remaining models and factories**

**Step 4: Run tests, Pint, commit**

---

### Task 3.3: MOM Service (CRUD + Status Workflow)

**Files:**
- Create: `app/Domain/Meeting/Services/MeetingService.php`
- Create: `app/Domain/Meeting/Services/VersionService.php`
- Test: `tests/Feature/Domain/Meeting/Services/MeetingServiceTest.php`

**Step 1: Write tests for CRUD and status transitions**

```php
test('can create a meeting', function () { ... });
test('can update a meeting in draft status', function () { ... });
test('cannot edit finalized meeting', function () { ... });
test('draft can transition to finalized', function () { ... });
test('finalized can transition to approved', function () { ... });
test('approved cannot transition back to draft', function () { ... });
test('creating version increments version number', function () { ... });
test('finalizing creates a version snapshot', function () { ... });
```

**Step 2: Implement MeetingService**

```php
class MeetingService
{
    public function __construct(
        private readonly VersionService $versionService,
        private readonly AuditService $auditService,
    ) {}

    public function create(array $data, User $user): MinutesOfMeeting { ... }
    public function update(MinutesOfMeeting $mom, array $data): MinutesOfMeeting { ... }
    public function finalize(MinutesOfMeeting $mom, User $user): MinutesOfMeeting { ... }
    public function approve(MinutesOfMeeting $mom, User $user): MinutesOfMeeting { ... }
    public function revertToDraft(MinutesOfMeeting $mom, User $user): MinutesOfMeeting { ... }
    public function delete(MinutesOfMeeting $mom): void { ... }
}
```

Status workflow validation:
- `draft` → `in_progress` → `finalized` → `approved`
- `finalized` → `draft` (revert, creates new version)
- `approved` is terminal

**Step 3: Run tests, Pint, commit**

---

### Task 3.4: MOM Policy, Form Requests, Controller

**Files:**
- Create: `app/Domain/Meeting/Policies/MinutesOfMeetingPolicy.php`
- Create: `app/Domain/Meeting/Requests/CreateMeetingRequest.php`
- Create: `app/Domain/Meeting/Requests/UpdateMeetingRequest.php`
- Create: `app/Domain/Meeting/Controllers/MeetingController.php`
- Create: `app/Domain/Meeting/Controllers/MeetingSeriesController.php`
- Create: `app/Domain/Meeting/Controllers/MeetingTemplateController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Meeting/Controllers/MeetingControllerTest.php`

**Step 1: Write HTTP tests**

```php
test('user can list meetings in their organization', function () { ... });
test('user can create a meeting', function () { ... });
test('user can view a meeting', function () { ... });
test('user can finalize a draft meeting', function () { ... });
test('user cannot see meetings from other organizations', function () { ... });
test('user can search meetings', function () { ... });
```

**Step 2: Implement policy**

Uses AuthorizationService to check role-based access.

**Step 3: Implement controllers and routes**

```php
Route::middleware(['auth', 'org.context'])->group(function () {
    // ... existing routes ...
    Route::resource('meetings', MeetingController::class);
    Route::post('meetings/{meeting}/finalize', [MeetingController::class, 'finalize'])->name('meetings.finalize');
    Route::post('meetings/{meeting}/approve', [MeetingController::class, 'approve'])->name('meetings.approve');
    Route::post('meetings/{meeting}/revert', [MeetingController::class, 'revert'])->name('meetings.revert');
    Route::resource('meeting-series', MeetingSeriesController::class);
    Route::resource('meeting-templates', MeetingTemplateController::class);
});
```

**Step 4: Run tests, Pint, commit**

---

### Task 3.5: MOM Search

**Files:**
- Create: `app/Domain/Meeting/Services/MeetingSearchService.php`
- Test: `tests/Feature/Domain/Meeting/Services/MeetingSearchServiceTest.php`

**Step 1: Write tests**

```php
test('can search meetings by title', function () { ... });
test('can filter meetings by status', function () { ... });
test('can filter meetings by date range', function () { ... });
test('can filter meetings by tags', function () { ... });
test('search is scoped to organization', function () { ... });
```

**Step 2: Implement search service**

Uses Eloquent query builder with full-text search on MySQL. Filters: status, date range, tags, series, created_by.

**Step 3: Run tests, Pint, commit**

---

## Week 4 — Transcription (Module B) & Multi-Input (Module D)

### Task 4.1: Transcription Migrations

**Files:**
- Create: `database/migrations/..._create_audio_transcriptions_table.php`
- Create: `database/migrations/..._create_transcription_segments_table.php`
- Create: `database/migrations/..._create_mom_inputs_table.php`
- Create: `database/migrations/..._create_mom_manual_notes_table.php`

**Step 1: Create audio_transcriptions migration**

```php
Schema::create('audio_transcriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
    $table->string('original_filename');
    $table->string('file_path');
    $table->string('mime_type');
    $table->unsignedBigInteger('file_size');
    $table->integer('duration_seconds')->nullable();
    $table->string('language')->default('en');
    $table->string('status')->default('pending');
    $table->longText('full_text')->nullable();
    $table->float('confidence_score')->nullable();
    $table->string('provider')->nullable();     // openai, google, ollama
    $table->json('provider_metadata')->nullable();
    $table->integer('retry_count')->default(0);
    $table->text('error_message')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
});
```

**Step 2: Create transcription_segments migration**

```php
Schema::create('transcription_segments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('audio_transcription_id')->constrained()->cascadeOnDelete();
    $table->text('text');
    $table->string('speaker')->nullable();
    $table->float('start_time');      // seconds
    $table->float('end_time');        // seconds
    $table->float('confidence')->nullable();
    $table->integer('sequence_order');
    $table->boolean('is_edited')->default(false);
    $table->timestamps();

    $table->index(['audio_transcription_id', 'sequence_order']);
});
```

**Step 3: Create mom_inputs migration**

```php
Schema::create('mom_inputs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->string('type');            // audio, document, manual_note, browser_recording
    $table->string('source_type')->nullable();    // polymorphic: AudioTranscription, MomManualNote, etc.
    $table->unsignedBigInteger('source_id')->nullable();
    $table->integer('sort_order')->default(0);
    $table->boolean('is_primary')->default(false);
    $table->timestamps();

    $table->index(['source_type', 'source_id']);
});
```

**Step 4: Create mom_manual_notes migration**

```php
Schema::create('mom_manual_notes', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->string('title')->nullable();
    $table->longText('content');
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**Step 5: Run migrations, Pint, commit**

---

### Task 4.2: Transcription Models & Factories

**Files:**
- Create: `app/Domain/Transcription/Models/AudioTranscription.php`
- Create: `app/Domain/Transcription/Models/TranscriptionSegment.php`
- Create: `app/Domain/Meeting/Models/MomInput.php`
- Create: `app/Domain/Meeting/Models/MomManualNote.php`
- Create: factories for each
- Test: `tests/Feature/Domain/Transcription/Models/TranscriptionModelsTest.php`

**Step 1: Write tests, implement models with proper relationships**

AudioTranscription: belongs to meeting, has many segments, casts status to TranscriptionStatus enum.

TranscriptionSegment: belongs to transcription, ordered by sequence_order.

MomInput: belongs to meeting, morphTo source.

**Step 2: Run tests, Pint, commit**

---

### Task 4.3: Transcription Service & Queue Jobs

**Files:**
- Create: `app/Domain/Transcription/Services/TranscriptionService.php`
- Create: `app/Domain/Transcription/Services/AudioStorageService.php`
- Create: `app/Domain/Transcription/Jobs/ProcessTranscriptionJob.php`
- Create: `app/Domain/Transcription/Events/TranscriptionCompleted.php`
- Create: `app/Domain/Transcription/Events/TranscriptionFailed.php`
- Test: `tests/Feature/Domain/Transcription/Services/TranscriptionServiceTest.php`
- Test: `tests/Feature/Domain/Transcription/Jobs/ProcessTranscriptionJobTest.php`

**Step 1: Write tests**

```php
test('can upload audio file and create transcription record', function () { ... });
test('transcription job dispatches to queue', function () { ... });
test('transcription job updates status on completion', function () { ... });
test('transcription job retries on failure', function () { ... });
test('transcription job marks failed after max retries', function () { ... });
```

**Step 2: Implement TranscriptionService**

```php
class TranscriptionService
{
    public function __construct(
        private readonly AudioStorageService $storage,
    ) {}

    public function upload(UploadedFile $file, MinutesOfMeeting $mom, User $user): AudioTranscription { ... }
    public function processTranscription(AudioTranscription $transcription): void { ... }
    public function getSegments(AudioTranscription $transcription): Collection { ... }
    public function updateSegment(TranscriptionSegment $segment, string $text): TranscriptionSegment { ... }
}
```

**Step 3: Implement ProcessTranscriptionJob**

```php
class ProcessTranscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public AudioTranscription $transcription,
    ) {}

    public function handle(TranscriberInterface $transcriber): void
    {
        $this->transcription->update(['status' => TranscriptionStatus::Processing, 'started_at' => now()]);

        try {
            $result = $transcriber->transcribe($this->transcription->file_path, [
                'language' => $this->transcription->language,
            ]);

            $this->transcription->update([
                'status' => TranscriptionStatus::Completed,
                'full_text' => $result->fullText,
                'confidence_score' => $result->confidence,
                'completed_at' => now(),
            ]);

            foreach ($result->segments as $i => $segment) {
                $this->transcription->segments()->create([
                    'text' => $segment->text,
                    'speaker' => $segment->speaker,
                    'start_time' => $segment->startTime,
                    'end_time' => $segment->endTime,
                    'confidence' => $segment->confidence,
                    'sequence_order' => $i,
                ]);
            }

            event(new TranscriptionCompleted($this->transcription));
        } catch (\Exception $e) {
            $this->transcription->update([
                'retry_count' => $this->transcription->retry_count + 1,
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->transcription->update([
            'status' => TranscriptionStatus::Failed,
            'error_message' => $exception->getMessage(),
        ]);

        event(new TranscriptionFailed($this->transcription));
    }
}
```

**Step 4: Run tests, Pint, commit**

---

### Task 4.4: Transcription Controller & Routes

**Files:**
- Create: `app/Domain/Transcription/Controllers/TranscriptionController.php`
- Create: `app/Domain/Transcription/Requests/UploadAudioRequest.php`
- Create: `app/Domain/Meeting/Controllers/ManualNoteController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Transcription/Controllers/TranscriptionControllerTest.php`

**Step 1: Write HTTP tests for upload flow**

**Step 2: Implement controller — delegates to TranscriptionService**

**Step 3: Add routes nested under meetings**

```php
Route::prefix('meetings/{meeting}')->group(function () {
    Route::resource('transcriptions', TranscriptionController::class)->only(['store', 'show', 'destroy']);
    Route::resource('manual-notes', ManualNoteController::class);
});
```

**Step 4: Run tests, Pint, commit**

---

## Week 5 — AI Extraction (Module C)

### Task 5.1: AI Extraction Migrations

**Files:**
- Create: `database/migrations/..._create_mom_extractions_table.php`
- Create: `database/migrations/..._create_mom_topics_table.php`

**Step 1: Create mom_extractions migration**

```php
Schema::create('mom_extractions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->string('type');            // summary, action_items, decisions, key_points
    $table->longText('content');
    $table->json('structured_data')->nullable();
    $table->string('provider');
    $table->string('model');
    $table->float('confidence_score')->nullable();
    $table->integer('token_usage')->nullable();
    $table->timestamps();

    $table->index(['minutes_of_meeting_id', 'type']);
});
```

**Step 2: Create mom_topics migration**

```php
Schema::create('mom_topics', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->integer('duration_minutes')->nullable();
    $table->integer('sort_order')->default(0);
    $table->json('related_segments')->nullable();
    $table->timestamps();
});
```

**Step 3: Run migrations, Pint, commit**

---

### Task 5.2: AI Extraction Models & Factories

**Files:**
- Create: `app/Domain/AI/Models/MomExtraction.php`
- Create: `app/Domain/AI/Models/MomTopic.php`
- Create: factories
- Test: `tests/Feature/Domain/AI/Models/AIModelsTest.php`

**Step 1: Write tests, implement models, factories**

**Step 2: Run tests, Pint, commit**

---

### Task 5.3: OpenAI Provider Implementation

**Files:**
- Modify: `app/Infrastructure/AI/Providers/OpenAIProvider.php`
- Create: `config/ai.php`
- Test: `tests/Feature/Infrastructure/AI/Providers/OpenAIProviderTest.php`

**Step 1: Create AI config file**

```php
// config/ai.php
return [
    'default' => env('AI_DEFAULT_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'transcription_model' => env('OPENAI_WHISPER_MODEL', 'whisper-1'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
        ],
        'google' => [
            'api_key' => env('GOOGLE_AI_API_KEY'),
            'model' => env('GOOGLE_AI_MODEL', 'gemini-2.0-flash'),
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_MODEL', 'llama3.2'),
        ],
    ],

    'prompts' => [
        'summarize' => env('AI_PROMPT_SUMMARIZE', 'Summarize the following meeting transcript concisely...'),
        'extract_actions' => env('AI_PROMPT_EXTRACT_ACTIONS', 'Extract all action items from the following meeting...'),
        'extract_decisions' => env('AI_PROMPT_EXTRACT_DECISIONS', 'Extract all decisions made in the following meeting...'),
    ],
];
```

**Step 2: Write integration tests (mock HTTP)**

```php
test('openai provider can summarize text', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Summary text']]],
            'usage' => ['total_tokens' => 150],
        ]),
    ]);

    $provider = new OpenAIProvider(apiKey: 'test-key');
    $result = $provider->summarize('Meeting transcript...');

    expect($result)->toBeInstanceOf(MeetingSummary::class);
    expect($result->summary)->toBe('Summary text');
});
```

**Step 3: Implement OpenAI provider with HTTP client**

Uses Laravel's Http facade to call OpenAI API. Structured prompts for each extraction type.

**Step 4: Implement Anthropic, Google, Ollama providers (same pattern)**

**Step 5: Run tests, Pint, commit**

---

### Task 5.4: AI Extraction Service & Jobs

**Files:**
- Create: `app/Domain/AI/Services/ExtractionService.php`
- Create: `app/Domain/AI/Jobs/ExtractMeetingDataJob.php`
- Create: `app/Domain/AI/Events/ExtractionCompleted.php`
- Test: `tests/Feature/Domain/AI/Services/ExtractionServiceTest.php`

**Step 1: Write tests**

```php
test('extraction service generates summary for meeting', function () { ... });
test('extraction service extracts action items', function () { ... });
test('extraction service extracts decisions', function () { ... });
test('extraction service identifies topics', function () { ... });
test('extraction job processes all extraction types', function () { ... });
```

**Step 2: Implement ExtractionService**

```php
class ExtractionService
{
    public function __construct(
        private readonly AIProviderFactory $factory,
    ) {}

    public function extractAll(MinutesOfMeeting $mom): void
    {
        $provider = $this->resolveProvider($mom->organization);
        $text = $this->getFullText($mom);

        $this->extractSummary($mom, $provider, $text);
        $this->extractActionItems($mom, $provider, $text);
        $this->extractDecisions($mom, $provider, $text);
        $this->extractTopics($mom, $provider, $text);
    }

    private function resolveProvider(Organization $org): AIProviderInterface { ... }
    private function getFullText(MinutesOfMeeting $mom): string { ... }
    private function extractSummary(MinutesOfMeeting $mom, AIProviderInterface $provider, string $text): void { ... }
    private function extractActionItems(MinutesOfMeeting $mom, AIProviderInterface $provider, string $text): void { ... }
    private function extractDecisions(MinutesOfMeeting $mom, AIProviderInterface $provider, string $text): void { ... }
    private function extractTopics(MinutesOfMeeting $mom, AIProviderInterface $provider, string $text): void { ... }
}
```

**Step 3: Implement ExtractMeetingDataJob (ShouldQueue)**

**Step 4: Run tests, Pint, commit**

---

### Task 5.5: AI Extraction Controller

**Files:**
- Create: `app/Domain/AI/Controllers/ExtractionController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/AI/Controllers/ExtractionControllerTest.php`

**Step 1: Write HTTP tests**

**Step 2: Implement controller — trigger extraction, view results**

```php
Route::prefix('meetings/{meeting}')->group(function () {
    Route::post('extract', [ExtractionController::class, 'extract'])->name('meetings.extract');
    Route::get('extractions', [ExtractionController::class, 'index'])->name('meetings.extractions.index');
});
```

**Step 3: Run tests, Pint, commit**

---

## Week 6 — AI Copilot (Module E) & Action Items (Module F)

### Task 6.1: AI Copilot Migrations & Models

**Files:**
- Create: `database/migrations/..._create_mom_ai_conversations_table.php`
- Create: `app/Domain/AI/Models/MomAiConversation.php`
- Create: factory
- Test: `tests/Feature/Domain/AI/Models/MomAiConversationTest.php`

**Step 1: Create migration**

```php
Schema::create('mom_ai_conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('role');            // user, assistant
    $table->longText('message');
    $table->json('context')->nullable();
    $table->integer('token_usage')->nullable();
    $table->string('provider')->nullable();
    $table->timestamps();

    $table->index(['minutes_of_meeting_id', 'user_id']);
});
```

**Step 2: Create model, factory, tests**

**Step 3: Run tests, Pint, commit**

---

### Task 6.2: AI Chat Service

**Files:**
- Create: `app/Domain/AI/Services/ChatService.php`
- Test: `tests/Feature/Domain/AI/Services/ChatServiceTest.php`

**Step 1: Write tests**

```php
test('can send message and receive AI response', function () { ... });
test('chat includes meeting context', function () { ... });
test('chat history is preserved', function () { ... });
```

**Step 2: Implement ChatService**

```php
class ChatService
{
    public function __construct(
        private readonly AIProviderFactory $factory,
    ) {}

    public function sendMessage(MinutesOfMeeting $mom, User $user, string $message): MomAiConversation
    {
        // Save user message
        $userMsg = $mom->aiConversations()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $message,
        ]);

        // Build context from meeting + chat history
        $context = $this->buildContext($mom, $user);

        // Get AI response
        $provider = $this->resolveProvider($mom->organization);
        $response = $provider->chat($message, $context);

        // Save assistant response
        return $mom->aiConversations()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'message' => $response,
            'provider' => $this->getProviderName($mom->organization),
        ]);
    }
}
```

**Step 3: Run tests, Pint, commit**

---

### Task 6.3: AI Chat Controller

**Files:**
- Create: `app/Domain/AI/Controllers/ChatController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/AI/Controllers/ChatControllerTest.php`

**Step 1: Write HTTP tests, implement controller**

```php
Route::prefix('meetings/{meeting}/chat')->group(function () {
    Route::get('/', [ChatController::class, 'index'])->name('meetings.chat.index');
    Route::post('/', [ChatController::class, 'store'])->name('meetings.chat.store');
});
```

**Step 2: Run tests, Pint, commit**

---

### Task 6.4: Action Items Migrations

**Files:**
- Create: `database/migrations/..._create_action_items_table.php`
- Create: `database/migrations/..._create_action_item_histories_table.php`

**Step 1: Create action_items migration**

```php
Schema::create('action_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->foreignId('carried_from_id')->nullable()->constrained('action_items')->nullOnDelete();
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('priority')->default('medium');
    $table->string('status')->default('open');
    $table->timestamp('due_date')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['organization_id', 'status']);
    $table->index(['assigned_to', 'status']);
    $table->index(['organization_id', 'due_date']);
});
```

**Step 2: Create action_item_histories migration**

```php
Schema::create('action_item_histories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('action_item_id')->constrained()->cascadeOnDelete();
    $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
    $table->string('field_changed');
    $table->text('old_value')->nullable();
    $table->text('new_value')->nullable();
    $table->text('comment')->nullable();
    $table->timestamps();
});
```

**Step 3: Run migrations, Pint, commit**

---

### Task 6.5: Action Item Models, Service, Controller

**Files:**
- Create: `app/Domain/ActionItem/Models/ActionItem.php`
- Create: `app/Domain/ActionItem/Models/ActionItemHistory.php`
- Create: `app/Domain/ActionItem/Services/ActionItemService.php`
- Create: `app/Domain/ActionItem/Controllers/ActionItemController.php`
- Create: `app/Domain/ActionItem/Controllers/ActionItemDashboardController.php`
- Create: `app/Domain/ActionItem/Requests/CreateActionItemRequest.php`
- Create: `app/Domain/ActionItem/Requests/UpdateActionItemRequest.php`
- Create: `app/Domain/ActionItem/Policies/ActionItemPolicy.php`
- Create: factories
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/ActionItem/Services/ActionItemServiceTest.php`
- Test: `tests/Feature/Domain/ActionItem/Controllers/ActionItemControllerTest.php`

**Step 1: Write tests**

```php
test('can create action item for meeting', function () { ... });
test('can update action item status', function () { ... });
test('status change creates history entry', function () { ... });
test('can carry forward action item to new meeting', function () { ... });
test('overdue items are identified correctly', function () { ... });
test('dashboard shows items across meetings', function () { ... });
test('action items are scoped to organization', function () { ... });
```

**Step 2: Implement ActionItemService**

```php
class ActionItemService
{
    public function create(array $data, MinutesOfMeeting $mom, User $user): ActionItem { ... }
    public function update(ActionItem $item, array $data, User $user): ActionItem { ... }
    public function changeStatus(ActionItem $item, ActionItemStatus $status, User $user, ?string $comment = null): ActionItem { ... }
    public function carryForward(ActionItem $item, MinutesOfMeeting $newMom, User $user): ActionItem { ... }
    public function getOverdueItems(Organization $org): Collection { ... }
    public function getDashboard(Organization $org, ?User $assignee = null): Collection { ... }
}
```

**Step 3: Implement controller, policy, requests, routes**

```php
Route::resource('action-items', ActionItemDashboardController::class)->only(['index']);

Route::prefix('meetings/{meeting}')->group(function () {
    Route::resource('action-items', ActionItemController::class);
    Route::post('action-items/{actionItem}/carry-forward', [ActionItemController::class, 'carryForward'])->name('action-items.carry-forward');
});
```

**Step 4: Run tests, Pint, commit**

---

## Week 7 — Attendees (Module G) & Frontend Foundation

### Task 7.1: Attendee Migrations

**Files:**
- Create: `database/migrations/..._create_attendee_groups_table.php`
- Create: `database/migrations/..._create_mom_attendees_table.php`
- Create: `database/migrations/..._create_mom_join_settings_table.php`

**Step 1: Create attendee_groups migration**

```php
Schema::create('attendee_groups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->json('default_members')->nullable();
    $table->timestamps();
});
```

**Step 2: Create mom_attendees migration**

```php
Schema::create('mom_attendees', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
    $table->string('name');
    $table->string('email')->nullable();
    $table->string('role')->default('participant');
    $table->string('rsvp_status')->default('pending');
    $table->boolean('is_present')->default(false);
    $table->boolean('is_external')->default(false);
    $table->string('department')->nullable();
    $table->timestamps();

    $table->unique(['minutes_of_meeting_id', 'email']);
});
```

**Step 3: Create mom_join_settings migration**

```php
Schema::create('mom_join_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('minutes_of_meeting_id')->constrained()->cascadeOnDelete();
    $table->boolean('allow_external_join')->default(false);
    $table->boolean('require_rsvp')->default(false);
    $table->boolean('auto_notify')->default(true);
    $table->json('notification_config')->nullable();
    $table->timestamps();
});
```

**Step 4: Run migrations, Pint, commit**

---

### Task 7.2: Attendee Models, Service, Controller

**Files:**
- Create: `app/Domain/Attendee/Models/MomAttendee.php`
- Create: `app/Domain/Attendee/Models/AttendeeGroup.php`
- Create: `app/Domain/Attendee/Models/MomJoinSetting.php`
- Create: `app/Domain/Attendee/Services/AttendeeService.php`
- Create: `app/Domain/Attendee/Controllers/AttendeeController.php`
- Create: `app/Domain/Attendee/Requests/AddAttendeeRequest.php`
- Create: `app/Domain/Attendee/Requests/BulkInviteRequest.php`
- Create: factories
- Modify: `routes/web.php`
- Test: `tests/Feature/Domain/Attendee/Services/AttendeeServiceTest.php`
- Test: `tests/Feature/Domain/Attendee/Controllers/AttendeeControllerTest.php`

**Step 1: Write tests**

```php
test('can add attendee to meeting', function () { ... });
test('can add external attendee', function () { ... });
test('can mark attendance', function () { ... });
test('can update RSVP status', function () { ... });
test('can bulk invite from group', function () { ... });
test('attendee uniqueness per meeting enforced', function () { ... });
```

**Step 2: Implement AttendeeService**

```php
class AttendeeService
{
    public function addAttendee(MinutesOfMeeting $mom, array $data): MomAttendee { ... }
    public function removeAttendee(MomAttendee $attendee): void { ... }
    public function updateRsvp(MomAttendee $attendee, RsvpStatus $status): MomAttendee { ... }
    public function markPresent(MomAttendee $attendee, bool $present = true): MomAttendee { ... }
    public function bulkInviteFromGroup(MinutesOfMeeting $mom, AttendeeGroup $group): Collection { ... }
}
```

**Step 3: Implement controller and routes**

```php
Route::prefix('meetings/{meeting}')->group(function () {
    Route::resource('attendees', AttendeeController::class);
    Route::post('attendees/bulk-invite', [AttendeeController::class, 'bulkInvite'])->name('attendees.bulk-invite');
    Route::patch('attendees/{attendee}/rsvp', [AttendeeController::class, 'updateRsvp'])->name('attendees.rsvp');
    Route::patch('attendees/{attendee}/presence', [AttendeeController::class, 'markPresence'])->name('attendees.presence');
});
```

**Step 4: Run tests, Pint, commit**

---

### Task 7.3: Blade Layout & Navigation

**Files:**
- Create: `resources/views/layouts/app.blade.php`
- Create: `resources/views/layouts/guest.blade.php`
- Create: `resources/views/components/navigation.blade.php`
- Create: `resources/views/components/sidebar.blade.php`
- Create: `resources/views/components/flash-message.blade.php`
- Modify: `resources/css/app.css` (Tailwind config)

**Step 1: Create app layout with sidebar navigation**

Main layout with:
- Sidebar: Dashboard, Meetings, Action Items, Templates, Settings
- Header: Organization switcher, user menu, notifications
- Main content area
- Flash messages

**Step 2: Create guest layout for login/register**

**Step 3: Commit**

```bash
git add -A && git commit -m "feat: add blade layouts with sidebar navigation"
```

---

### Task 7.4: Dashboard View

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`
- Create: `resources/views/dashboard.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/DashboardTest.php`

**Step 1: Write test**

```php
test('authenticated user sees dashboard', function () {
    $user = User::factory()->withOrganization()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertViewIs('dashboard');
});
```

**Step 2: Implement dashboard**

Shows: recent meetings, upcoming action items, quick create meeting button.

**Step 3: Run tests, Pint, commit**

---

### Task 7.5: Meeting Views (CRUD)

**Files:**
- Create: `resources/views/meetings/index.blade.php`
- Create: `resources/views/meetings/create.blade.php`
- Create: `resources/views/meetings/show.blade.php`
- Create: `resources/views/meetings/edit.blade.php`
- Create: `resources/views/meetings/partials/form.blade.php`
- Create: `resources/views/meetings/partials/status-badge.blade.php`
- Create: `resources/views/meetings/partials/version-history.blade.php`

**Step 1: Create meeting views with Alpine.js interactions**

- Index: List with filters (status, date, tags), search, pagination
- Create/Edit: Form with title, date, location, duration, template select, series select
- Show: Full meeting view with tabs (Content, Transcription, AI, Action Items, Attendees)

**Step 2: Commit**

---

### Task 7.6: Meeting Show Page — Tabbed Interface with Alpine.js

**Files:**
- Create: `resources/views/meetings/tabs/content.blade.php`
- Create: `resources/views/meetings/tabs/transcription.blade.php`
- Create: `resources/views/meetings/tabs/ai-extraction.blade.php`
- Create: `resources/views/meetings/tabs/action-items.blade.php`
- Create: `resources/views/meetings/tabs/attendees.blade.php`
- Create: `resources/views/meetings/tabs/chat.blade.php`
- Create: `resources/js/components/tab-panel.js`
- Create: `resources/js/components/audio-recorder.js`
- Create: `resources/js/components/ai-chat.js`

**Step 1: Create tabbed interface using Alpine.js**

```html
<div x-data="{ activeTab: 'content' }">
    <nav>
        <button @click="activeTab = 'content'" :class="activeTab === 'content' ? 'active' : ''">Content</button>
        <!-- ... more tabs ... -->
    </nav>
    <div x-show="activeTab === 'content'">@include('meetings.tabs.content')</div>
    <!-- ... more panels ... -->
</div>
```

**Step 2: Create audio recorder component (vanilla JS)**

Browser MediaRecorder API integration for in-browser recording.

**Step 3: Create AI chat component (Alpine.js + fetch)**

Real-time chat interface using fetch POST to chat endpoint.

**Step 4: Commit**

---

## Week 8 — Integration, Polish & Full Test Suite

### Task 8.1: Notifications Migration & Model

**Files:**
- Create: `database/migrations/..._create_notifications_table.php` (if not using Laravel's built-in)
- Use Laravel's notification system for email + database notifications

**Step 1: Create notification classes**

```php
// app/Domain/ActionItem/Notifications/ActionItemOverdueNotification.php
// app/Domain/ActionItem/Notifications/ActionItemAssignedNotification.php
// app/Domain/Meeting/Notifications/MeetingFinalizedNotification.php
// app/Domain/Attendee/Notifications/MeetingInviteNotification.php
```

**Step 2: Run tests, Pint, commit**

---

### Task 8.2: Overdue Action Items Scheduler

**Files:**
- Create: `app/Domain/ActionItem/Jobs/CheckOverdueActionItemsJob.php`
- Modify: `routes/console.php` (schedule command)
- Test: `tests/Feature/Domain/ActionItem/Jobs/CheckOverdueActionItemsJobTest.php`

**Step 1: Write tests**

```php
test('overdue job sends notifications for past-due items', function () { ... });
test('overdue job ignores completed items', function () { ... });
```

**Step 2: Implement scheduled job**

```php
// routes/console.php
Schedule::job(new CheckOverdueActionItemsJob)->dailyAt('08:00');
```

**Step 3: Run tests, Pint, commit**

---

### Task 8.3: Service Provider Bindings

**Files:**
- Create: `app/Providers/DomainServiceProvider.php`
- Modify: `bootstrap/providers.php`

**Step 1: Register interface bindings**

```php
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AIProviderInterface::class, function ($app) {
            $defaultProvider = config('ai.default');
            $config = config("ai.providers.{$defaultProvider}");

            return AIProviderFactory::make($defaultProvider, $config);
        });

        $this->app->bind(TranscriberInterface::class, function ($app) {
            // Default to OpenAI Whisper
            return new OpenAITranscriber(config('ai.providers.openai.api_key'));
        });
    }

    /** @return array<class-string, class-string> */
    public array $singletons = [
        AuditService::class => AuditService::class,
        AuthorizationService::class => AuthorizationService::class,
    ];
}
```

**Step 2: Register in bootstrap/providers.php**

```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\DomainServiceProvider::class,
];
```

**Step 3: Run tests, Pint, commit**

---

### Task 8.4: Database Seeders

**Files:**
- Create: `database/seeders/SubscriptionPlanSeeder.php`
- Create: `database/seeders/DemoOrganizationSeeder.php`
- Create: `database/seeders/DemoMeetingSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Step 1: Create comprehensive seeders**

- 4 subscription plans (Free, Pro, Business, Enterprise)
- Demo organization with multiple users (various roles)
- 5-10 sample meetings with different statuses
- Sample action items, attendees, tags
- AI provider config (OpenAI as default)

**Step 2: Run seeders, commit**

```bash
php artisan db:seed --no-interaction
git add -A && git commit -m "feat: add comprehensive seeders for development"
```

---

### Task 8.5: End-to-End Feature Tests

**Files:**
- Create: `tests/Feature/Flows/MeetingLifecycleTest.php`
- Create: `tests/Feature/Flows/ActionItemWorkflowTest.php`
- Create: `tests/Feature/Flows/TranscriptionPipelineTest.php`
- Create: `tests/Feature/Flows/MultiTenancyIsolationTest.php`

**Step 1: Write flow tests**

```php
// tests/Feature/Flows/MeetingLifecycleTest.php
test('full meeting lifecycle: create → edit → add attendees → add transcription → extract AI → finalize → approve', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->withOrganization($org)->create();
    $user->organizations()->attach($org, ['role' => 'owner']);

    // Create meeting
    $response = $this->actingAs($user)->post('/meetings', [
        'title' => 'Sprint Planning',
        'meeting_date' => now()->addDay(),
    ]);
    $response->assertRedirect();
    $meeting = MinutesOfMeeting::query()->first();
    expect($meeting->status)->toBe(MeetingStatus::Draft);

    // Add attendee
    $this->actingAs($user)->post("/meetings/{$meeting->id}/attendees", [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ])->assertRedirect();

    // Add manual note
    $this->actingAs($user)->post("/meetings/{$meeting->id}/manual-notes", [
        'content' => 'Discussed sprint goals...',
    ])->assertRedirect();

    // Finalize
    $this->actingAs($user)->post("/meetings/{$meeting->id}/finalize")
        ->assertRedirect();
    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Finalized);

    // Approve
    $this->actingAs($user)->post("/meetings/{$meeting->id}/approve")
        ->assertRedirect();
    $meeting->refresh();
    expect($meeting->status)->toBe(MeetingStatus::Approved);
});
```

```php
// tests/Feature/Flows/MultiTenancyIsolationTest.php
test('users cannot see other organizations meetings', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user1 = User::factory()->create(['current_organization_id' => $org1->id]);
    $user2 = User::factory()->create(['current_organization_id' => $org2->id]);

    $meeting = MinutesOfMeeting::factory()->create(['organization_id' => $org1->id]);

    $this->actingAs($user2)->get("/meetings/{$meeting->id}")
        ->assertForbidden();
});
```

**Step 2: Run full test suite**

```bash
php artisan test --compact
```

Expected: All tests pass, 80%+ coverage.

**Step 3: Run Pint on entire project**

```bash
vendor/bin/pint --format agent
```

**Step 4: Commit**

```bash
git add -A && git commit -m "feat: add end-to-end flow tests for meeting lifecycle, tenancy isolation"
```

---

### Task 8.6: Route Summary & Config Cleanup

**Files:**
- Modify: `routes/web.php` (clean up, organize)
- Modify: `.env.example` (add AI config vars)

**Step 1: Organize routes by domain**

```php
// routes/web.php

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register']);
});

// Authenticated routes
Route::middleware(['auth', 'org.context'])->group(function () {
    Route::post('logout', [LogoutController::class, 'logout'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Account
    Route::resource('organizations', OrganizationController::class);
    Route::resource('organizations.members', MemberController::class)->shallow();

    // Meetings
    Route::resource('meetings', MeetingController::class);
    Route::post('meetings/{meeting}/finalize', [MeetingController::class, 'finalize'])->name('meetings.finalize');
    Route::post('meetings/{meeting}/approve', [MeetingController::class, 'approve'])->name('meetings.approve');
    Route::post('meetings/{meeting}/revert', [MeetingController::class, 'revert'])->name('meetings.revert');
    Route::resource('meeting-series', MeetingSeriesController::class);
    Route::resource('meeting-templates', MeetingTemplateController::class);

    // Meeting sub-resources
    Route::prefix('meetings/{meeting}')->group(function () {
        Route::resource('transcriptions', TranscriptionController::class)->only(['store', 'show', 'destroy']);
        Route::resource('manual-notes', ManualNoteController::class);
        Route::resource('attendees', AttendeeController::class);
        Route::post('attendees/bulk-invite', [AttendeeController::class, 'bulkInvite'])->name('attendees.bulk-invite');
        Route::resource('action-items', ActionItemController::class);
        Route::post('action-items/{actionItem}/carry-forward', [ActionItemController::class, 'carryForward'])->name('action-items.carry-forward');
        Route::post('extract', [ExtractionController::class, 'extract'])->name('meetings.extract');
        Route::get('extractions', [ExtractionController::class, 'index'])->name('meetings.extractions.index');
        Route::get('chat', [ChatController::class, 'index'])->name('meetings.chat.index');
        Route::post('chat', [ChatController::class, 'store'])->name('meetings.chat.store');
    });

    // Cross-meeting dashboards
    Route::get('action-items', [ActionItemDashboardController::class, 'index'])->name('action-items.dashboard');
});
```

**Step 2: Update .env.example**

Add AI provider configuration variables.

**Step 3: Run Pint, commit**

```bash
vendor/bin/pint --format agent
git add -A && git commit -m "feat: organize routes, update env template with AI config"
```

---

## File Count Summary

| Domain | Models | Migrations | Services | Controllers | Tests |
|--------|--------|-----------|----------|-------------|-------|
| Account | 6 | 7 | 3 | 5 | 5+ |
| Meeting | 5 | 5 | 3 | 3 | 4+ |
| Transcription | 2 | 2 | 2 | 1 | 3+ |
| AI | 3 | 3 | 2 | 2 | 4+ |
| ActionItem | 2 | 2 | 1 | 2 | 3+ |
| Attendee | 3 | 3 | 1 | 1 | 2+ |
| Infrastructure | 6+ | 0 | 0 | 0 | 2+ |
| Support | 9 enums | 0 | 0 | 0 | 1+ |
| **Total** | **~36** | **~22** | **~12** | **~14** | **~24+** |

## Test Strategy

| Layer | Approach | Tools |
|-------|----------|-------|
| Unit | Enum validation, DTO creation, factory states | Pest |
| Feature/Model | Relationship tests, scope tests, cast tests | Pest + RefreshDatabase |
| Feature/Service | Business logic, status workflows, authorization | Pest + RefreshDatabase |
| Feature/Controller | HTTP tests, validation, redirects, auth guards | Pest + RefreshDatabase |
| Flow/E2E | Full lifecycle tests, multi-tenancy isolation | Pest + RefreshDatabase |
| AI Integration | Mock HTTP responses, verify prompts/parsing | Pest + Http::fake() |

**Coverage target:** 80%+ across all domains.

**Test naming:** `test('[action] [expected result]', function () { ... });`

**Every test file** uses Pest syntax with `uses(RefreshDatabase::class)` for database tests.

---

## Critical Path Dependencies

```
Week 1: Foundation ──────────────────────────┐
  ├─ Enums, Tenancy trait, AI contracts      │
  └─ Foundation migrations & Organization     │
                                              │
Week 2: Account Module ◄─────────────────────┘
  ├─ RBAC, Auth, Policies                    │
  └─ Services, Controllers                   │
                                              │
Week 3: Core MOM ◄───────────────────────────┘
  ├─ Migrations, Models, CRUD               │
  ├─ Status workflow service                 │
  └─ Search service                          │
                                              │
Week 4: Transcription + Multi-Input ◄─────────┘
  ├─ Audio upload, queue jobs                │
  └─ Manual notes, input types               │
                                              │
Week 5: AI Extraction ◄──────────────────────┘
  ├─ Provider implementations               │
  └─ Extraction pipeline                     │
                                              │
Week 6: AI Copilot + Action Items ◄───────────┘
  ├─ Chat service                            │
  └─ Action item tracking                    │
                                              │
Week 7: Attendees + Frontend ◄────────────────┘
  ├─ Attendee management                     │
  └─ Blade views, Alpine.js components       │
                                              │
Week 8: Integration + Polish ◄────────────────┘
  ├─ Notifications, scheduler               │
  ├─ Seeders, service provider bindings      │
  └─ E2E tests, route cleanup               │
```
