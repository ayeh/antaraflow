<?php

declare(strict_types=1);

use App\Domain\Admin\Services\AiControlService;
use App\Domain\AI\Models\AiUsageLog;
use App\Domain\AI\Notifications\AiBudgetAlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function seedSpend(float $amount): void
{
    AiUsageLog::query()->create([
        'provider' => 'openai',
        'model' => 'gpt-5.4-mini',
        'operation' => 'chat',
        'cost' => $amount,
    ]);
}

test('command sends warning when daily budget reached but keeps ai enabled', function () {
    Notification::fake();

    $control = app(AiControlService::class);
    $control->setDailyBudget(10);
    $control->setHardCap(0);
    $control->setAlertEmail('ops@example.com');

    seedSpend(12.00);

    $this->artisan('ai:check-budget')->assertSuccessful();

    expect($control->isEnabled())->toBeTrue();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        AiBudgetAlertNotification::class,
        fn ($notification) => $notification->level === 'warning' && ! $notification->autoDisabled,
    );
});

test('command auto-disables ai and sends critical alert when hard cap exceeded', function () {
    Notification::fake();

    $control = app(AiControlService::class);
    $control->setHardCap(50);
    $control->setAlertEmail('ops@example.com');

    seedSpend(60.00);

    $this->artisan('ai:check-budget')->assertSuccessful();

    expect($control->isEnabled())->toBeFalse();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        AiBudgetAlertNotification::class,
        fn ($notification) => $notification->level === 'critical' && $notification->autoDisabled,
    );
});

test('command does nothing when spend is within budget', function () {
    Notification::fake();

    $control = app(AiControlService::class);
    $control->setDailyBudget(100);
    $control->setHardCap(200);
    $control->setAlertEmail('ops@example.com');

    seedSpend(5.00);

    $this->artisan('ai:check-budget')->assertSuccessful();

    expect($control->isEnabled())->toBeTrue();
    Notification::assertNothingSent();
});

test('command skips notification when no recipients configured', function () {
    Notification::fake();

    $control = app(AiControlService::class);
    $control->setDailyBudget(10);

    seedSpend(20.00);

    $this->artisan('ai:check-budget')->assertSuccessful();

    Notification::assertNothingSent();
});
