<?php

declare(strict_types=1);

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\PlatformSetting;

class AiControlService
{
    private const KEY_ENABLED = 'ai_enabled';

    private const KEY_DAILY_BUDGET = 'ai_daily_budget';

    private const KEY_HARD_CAP = 'ai_hard_cap';

    private const KEY_ALERT_EMAIL = 'ai_alert_email';

    private const KEY_ALERT_TELEGRAM = 'ai_alert_telegram_chat_id';

    public function isEnabled(): bool
    {
        return (bool) PlatformSetting::getValue(self::KEY_ENABLED, true);
    }

    public function setEnabled(bool $enabled): void
    {
        PlatformSetting::setValue(self::KEY_ENABLED, $enabled);
    }

    public function enable(): void
    {
        $this->setEnabled(true);
    }

    public function disable(): void
    {
        $this->setEnabled(false);
    }

    /**
     * Soft daily budget in USD that triggers an alert. Zero disables the alert.
     */
    public function dailyBudget(): float
    {
        return (float) PlatformSetting::getValue(self::KEY_DAILY_BUDGET, 0);
    }

    public function setDailyBudget(float $amount): void
    {
        PlatformSetting::setValue(self::KEY_DAILY_BUDGET, $amount);
    }

    /**
     * Hard daily cap in USD that auto-disables AI when exceeded. Zero disables the cap.
     */
    public function hardCap(): float
    {
        return (float) PlatformSetting::getValue(self::KEY_HARD_CAP, 0);
    }

    public function setHardCap(float $amount): void
    {
        PlatformSetting::setValue(self::KEY_HARD_CAP, $amount);
    }

    public function alertEmail(): ?string
    {
        $value = PlatformSetting::getValue(self::KEY_ALERT_EMAIL);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setAlertEmail(?string $email): void
    {
        PlatformSetting::setValue(self::KEY_ALERT_EMAIL, $email ?? '');
    }

    public function alertTelegramChatId(): ?string
    {
        $value = PlatformSetting::getValue(self::KEY_ALERT_TELEGRAM);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setAlertTelegramChatId(?string $chatId): void
    {
        PlatformSetting::setValue(self::KEY_ALERT_TELEGRAM, $chatId ?? '');
    }
}
