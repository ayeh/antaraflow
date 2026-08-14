<?php

declare(strict_types=1);

use App\Domain\Admin\Services\AiControlService;
use App\Domain\AI\Models\AiUsageLog;
use App\Domain\AI\Notifications\AiBudgetAlertNotification;
use App\Domain\AI\Services\AiUsageRecorder;
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

function seedSpendOn(float $amount, int $daysAgo): void
{
    $log = AiUsageLog::query()->create([
        'provider' => 'openai',
        'model' => 'gpt-5.4-mini',
        'operation' => 'chat',
        'cost' => $amount,
    ]);
    $log->forceFill(['created_at' => now()->subDays($daysAgo)->setTime(12, 0)])->save();
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

test('anomaly detection alerts when today exceeds the rolling baseline', function () {
    Notification::fake();

    $control = app(AiControlService::class);
    $control->setAnomalyEnabled(true);
    $control->setAnomalyMultiplier(2.0);
    $control->setAlertEmail('ops@example.com');

    for ($d = 1; $d <= 7; $d++) {
        seedSpendOn(10.0, $d); // baseline ≈ $10/day
    }
    seedSpend(30.00); // today, ≥ 2× baseline

    $this->artisan('ai:check-budget')->assertSuccessful();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        AiBudgetAlertNotification::class,
        fn ($notification) => $notification->level === 'anomaly',
    );
});

test('anomaly detection stays quiet within the baseline band', function () {
    Notification::fake();

    $control = app(AiControlService::class);
    $control->setAnomalyEnabled(true);
    $control->setAnomalyMultiplier(2.0);
    $control->setAlertEmail('ops@example.com');

    for ($d = 1; $d <= 7; $d++) {
        seedSpendOn(10.0, $d);
    }
    seedSpend(15.00); // below 2× baseline

    $this->artisan('ai:check-budget')->assertSuccessful();

    Notification::assertNothingSent();
});

test('daily series returns a zero-filled 30-day window for charting', function () {
    seedSpend(5.0);        // today
    seedSpendOn(3.0, 2);   // 2 days ago

    $series = app(AiUsageRecorder::class)->dailySeries(30);

    expect($series)->toHaveCount(30);
    expect($series[now()->toDateString()])->toBe(5.0);
    expect($series[now()->subDays(2)->toDateString()])->toBe(3.0);
    expect($series[now()->subDays(5)->toDateString()])->toBe(0.0);
});

test('anomaly detection is skipped when disabled', function () {
    Notification::fake();

    $control = app(AiControlService::class);
    $control->setAnomalyEnabled(false);
    $control->setAlertEmail('ops@example.com');

    for ($d = 1; $d <= 7; $d++) {
        seedSpendOn(10.0, $d);
    }
    seedSpend(30.00);

    $this->artisan('ai:check-budget')->assertSuccessful();

    Notification::assertNothingSent();
});

// The baseline averages in days with no usage at all, so after a quiet week it
// sits near zero and the first ordinary day of work clears any multiple of it.
// Measured on a month of real spend, that fired on a fifth of all days at 2x
// and still fired at 10x, over amounts under three dollars. An alert that
// arrives when nothing is wrong is the one people learn to ignore.
test('a small day after a quiet week is not an anomaly', function () {
    Notification::fake();

    $control = app(AiControlService::class);
    $control->setAnomalyEnabled(true);
    $control->setAnomalyMultiplier(2.0);
    $control->setAnomalyMinSpend(5.0);
    $control->setAlertEmail('ops@example.com');

    // Six quiet days and one small one: baseline lands at about 7 cents.
    seedSpendOn(0.50, 3);
    seedSpend(2.00); // twenty-eight times the baseline, and still nothing to see

    $this->artisan('ai:check-budget')->assertSuccessful();

    Notification::assertNothingSent();
});

test('a genuinely large day still alerts through the floor', function () {
    Notification::fake();

    $control = app(AiControlService::class);
    $control->setAnomalyEnabled(true);
    $control->setAnomalyMultiplier(2.0);
    $control->setAnomalyMinSpend(5.0);
    $control->setAlertEmail('ops@example.com');

    for ($d = 1; $d <= 7; $d++) {
        seedSpendOn(10.0, $d);
    }
    seedSpend(30.00);

    $this->artisan('ai:check-budget')->assertSuccessful();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        AiBudgetAlertNotification::class,
        fn ($notification) => $notification->level === 'anomaly',
    );
});

// Everything recorded before this setting existed reads zero, which is the
// behaviour those installations already had.
test('a floor of zero leaves the check exactly as it was', function () {
    Notification::fake();

    $control = app(AiControlService::class);
    $control->setAnomalyEnabled(true);
    $control->setAnomalyMultiplier(2.0);
    $control->setAlertEmail('ops@example.com');

    expect($control->anomalyMinSpend())->toBe(0.0);

    seedSpendOn(0.50, 3);
    seedSpend(2.00);

    $this->artisan('ai:check-budget')->assertSuccessful();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        AiBudgetAlertNotification::class,
        fn ($notification) => $notification->level === 'anomaly',
    );
});
