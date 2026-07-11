<?php

declare(strict_types=1);

use App\Domain\Admin\Models\Admin;
use App\Domain\Admin\Services\AiControlService;
use App\Domain\AI\Models\AiUsageLog;
use App\Domain\AI\Notifications\AiBudgetAlertNotification;
use App\Domain\AI\Services\AiPricingService;
use App\Domain\AI\Services\AiUsageRecorder;
use App\Infrastructure\AI\Contracts\AIProviderInterface;
use App\Infrastructure\AI\Contracts\TranscriberInterface;
use App\Infrastructure\AI\Exceptions\AiDisabledException;
use App\Infrastructure\AI\Providers\DisabledAIProvider;
use App\Infrastructure\AI\Providers\DisabledTranscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

test('ai is enabled by default', function () {
    expect(app(AiControlService::class)->isEnabled())->toBeTrue();
});

test('disabling ai binds the null provider that throws', function () {
    app(AiControlService::class)->disable();

    $provider = app(AIProviderInterface::class);

    expect($provider)->toBeInstanceOf(DisabledAIProvider::class);
    expect(fn () => $provider->chat('hello'))->toThrow(AiDisabledException::class);
});

test('disabling ai binds the null transcriber that throws', function () {
    app(AiControlService::class)->disable();

    $transcriber = app(TranscriberInterface::class);

    expect($transcriber)->toBeInstanceOf(DisabledTranscriber::class);
    expect(fn () => $transcriber->transcribe('/tmp/x.wav'))->toThrow(AiDisabledException::class);
});

test('pricing service computes chat cost from token counts', function () {
    $cost = app(AiPricingService::class)->chatCost('gpt-5.4-mini', 1_000_000, 1_000_000);

    // $0.75 input + $4.50 output per 1M tokens
    expect($cost)->toBe(5.25);
});

test('pricing service returns zero for unknown model', function () {
    expect(app(AiPricingService::class)->chatCost('made-up-model', 1000, 1000))->toBe(0.0);
});

test('usage recorder persists a log with computed cost', function () {
    app(AiUsageRecorder::class)->recordChat('openai', 'gpt-5.4-nano', 1_000_000, 1_000_000);

    $log = AiUsageLog::query()->firstOrFail();

    expect($log->total_tokens)->toBe(2_000_000);
    // $0.20 + $1.25 per 1M
    expect((float) $log->cost)->toBe(1.45);
});

test('today spend sums only todays logs', function () {
    AiUsageLog::query()->create(['provider' => 'openai', 'model' => 'gpt-5.4-mini', 'operation' => 'chat', 'cost' => 3.00]);
    AiUsageLog::query()->create(['provider' => 'openai', 'model' => 'gpt-5.4-mini', 'operation' => 'chat', 'cost' => 2.00]);

    $yesterday = AiUsageLog::query()->create(['provider' => 'openai', 'model' => 'gpt-5.4-mini', 'operation' => 'chat', 'cost' => 99.00]);
    $yesterday->forceFill(['created_at' => now()->subDay()])->save();

    expect(app(AiUsageRecorder::class)->todaySpend())->toBe(5.0);
});

test('admin can view the ai control page', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.ai.index'))
        ->assertStatus(200)
        ->assertSee('AI Features');
});

test('admin can toggle ai off and on', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.ai.toggle'), ['enabled' => '0'])
        ->assertRedirect(route('admin.ai.index'));

    expect(app(AiControlService::class)->isEnabled())->toBeFalse();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.ai.toggle'), ['enabled' => '1'])
        ->assertRedirect(route('admin.ai.index'));

    expect(app(AiControlService::class)->isEnabled())->toBeTrue();
});

test('admin can save budget and alert settings', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->put(route('admin.ai.update-settings'), [
            'daily_budget' => 25.50,
            'hard_cap' => 100,
            'alert_email' => 'ops@example.com',
            'alert_telegram_chat_id' => '-100123',
            'credit_topup' => 100,
            'credit_topup_date' => '2026-07-01',
        ])
        ->assertRedirect(route('admin.ai.index'));

    $control = app(AiControlService::class);
    expect($control->dailyBudget())->toBe(25.5);
    expect($control->hardCap())->toBe(100.0);
    expect($control->alertEmail())->toBe('ops@example.com');
    expect($control->alertTelegramChatId())->toBe('-100123');
    expect($control->creditTopup())->toBe(100.0);
    expect($control->creditTopupDate())->toBe('2026-07-01');
});

test('test alert sends to configured email', function () {
    Notification::fake();

    $admin = Admin::factory()->create();
    app(AiControlService::class)->setAlertEmail('ops@example.com');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.ai.test-alert'))
        ->assertRedirect(route('admin.ai.index'))
        ->assertSessionHas('success');

    Notification::assertSentTo(new AnonymousNotifiable, AiBudgetAlertNotification::class);
});

test('test alert errors when no recipients configured', function () {
    Notification::fake();

    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.ai.test-alert'))
        ->assertRedirect(route('admin.ai.index'))
        ->assertSessionHas('error');

    Notification::assertNothingSent();
});

test('test alert errors when telegram set but bot token missing', function () {
    Notification::fake();
    config()->set('services.telegram.bot_token', null);

    $admin = Admin::factory()->create();
    app(AiControlService::class)->setAlertTelegramChatId('-100123');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.ai.test-alert'))
        ->assertRedirect(route('admin.ai.index'))
        ->assertSessionHas('error');

    Notification::assertNothingSent();
});
