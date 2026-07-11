<?php

declare(strict_types=1);

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\PlatformSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class AiControlService
{
    /**
     * Providers whose active selection, model and API key may be managed from
     * the admin UI. Local providers (ollama, whisper_local) are env-only.
     *
     * @var list<string>
     */
    public const MANAGED_PROVIDERS = ['openai', 'anthropic', 'google'];

    private const KEY_ENABLED = 'ai_enabled';

    private const KEY_ACTIVE_PROVIDER = 'ai_active_provider';

    private const KEY_PROVIDER_MODELS = 'ai_provider_models';

    private const KEY_PROVIDER_KEYS = 'ai_provider_keys';

    private const KEY_DAILY_BUDGET = 'ai_daily_budget';

    private const KEY_HARD_CAP = 'ai_hard_cap';

    private const KEY_ALERT_EMAIL = 'ai_alert_email';

    private const KEY_ALERT_TELEGRAM = 'ai_alert_telegram_chat_id';

    private const KEY_CREDIT_TOPUP = 'ai_credit_topup';

    private const KEY_CREDIT_TOPUP_DATE = 'ai_credit_topup_date';

    private const KEY_ANOMALY_ENABLED = 'ai_anomaly_enabled';

    private const KEY_ANOMALY_MULTIPLIER = 'ai_anomaly_multiplier';

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

    /**
     * Amount (USD) the org last topped up on OpenAI. Used to estimate the
     * remaining balance, since OpenAI exposes no balance endpoint.
     */
    public function creditTopup(): float
    {
        return (float) PlatformSetting::getValue(self::KEY_CREDIT_TOPUP, 0);
    }

    public function creditTopupDate(): ?string
    {
        $value = PlatformSetting::getValue(self::KEY_CREDIT_TOPUP_DATE);

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    public function setCreditTopup(float $amount, ?string $date): void
    {
        PlatformSetting::setValue(self::KEY_CREDIT_TOPUP, $amount);
        PlatformSetting::setValue(self::KEY_CREDIT_TOPUP_DATE, $date ?? '');
    }

    /**
     * When enabled, today's spend is compared against a rolling 7-day daily
     * average and alerts when it exceeds that baseline by the multiplier.
     */
    public function anomalyEnabled(): bool
    {
        return (bool) PlatformSetting::getValue(self::KEY_ANOMALY_ENABLED, false);
    }

    public function setAnomalyEnabled(bool $enabled): void
    {
        PlatformSetting::setValue(self::KEY_ANOMALY_ENABLED, $enabled);
    }

    public function anomalyMultiplier(): float
    {
        $value = (float) PlatformSetting::getValue(self::KEY_ANOMALY_MULTIPLIER, 2.0);

        return $value > 0 ? $value : 2.0;
    }

    public function setAnomalyMultiplier(float $multiplier): void
    {
        PlatformSetting::setValue(self::KEY_ANOMALY_MULTIPLIER, $multiplier);
    }

    /**
     * The active provider override chosen in the UI, or null to fall back to
     * the AI_DEFAULT_PROVIDER env value.
     */
    public function activeProviderOverride(): ?string
    {
        $value = PlatformSetting::getValue(self::KEY_ACTIVE_PROVIDER);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function setActiveProvider(?string $provider): void
    {
        $provider = $provider !== null && in_array($provider, self::MANAGED_PROVIDERS, true) ? $provider : '';
        PlatformSetting::setValue(self::KEY_ACTIVE_PROVIDER, $provider);
    }

    /**
     * Per-provider model overrides, keyed by provider name.
     *
     * @return array<string, string>
     */
    public function modelOverrides(): array
    {
        $value = PlatformSetting::getValue(self::KEY_PROVIDER_MODELS, []);

        return is_array($value) ? array_filter($value, 'is_string') : [];
    }

    public function setModel(string $provider, ?string $model): void
    {
        $models = $this->modelOverrides();

        if ($model === null || trim($model) === '') {
            unset($models[$provider]);
        } else {
            $models[$provider] = trim($model);
        }

        PlatformSetting::setValue(self::KEY_PROVIDER_MODELS, $models);
    }

    /**
     * Whether an API key override is stored in the database for a provider.
     */
    public function hasKeyOverride(string $provider): bool
    {
        $keys = PlatformSetting::getValue(self::KEY_PROVIDER_KEYS, []);

        return is_array($keys) && ! empty($keys[$provider]);
    }

    /**
     * Store (encrypted) or clear the API key override for a provider. Passing
     * null or an empty string leaves any existing key untouched; use
     * clearKey() to remove one.
     */
    public function setApiKey(string $provider, ?string $key): void
    {
        if ($key === null || trim($key) === '') {
            return;
        }

        $keys = $this->rawKeyMap();
        $keys[$provider] = Crypt::encryptString(trim($key));
        PlatformSetting::setValue(self::KEY_PROVIDER_KEYS, $keys);
    }

    public function clearKey(string $provider): void
    {
        $keys = $this->rawKeyMap();
        unset($keys[$provider]);
        PlatformSetting::setValue(self::KEY_PROVIDER_KEYS, $keys);
    }

    /**
     * Push DB-managed provider, model and key overrides into the runtime
     * config so every `config('ai...')` consumer reflects them. Guarded so it
     * is a no-op before the settings table exists (e.g. during migrations).
     */
    public function applyRuntimeOverrides(): void
    {
        if (! $this->settingsTableReady()) {
            return;
        }

        if ($provider = $this->activeProviderOverride()) {
            config(['ai.default' => $provider]);
        }

        foreach ($this->modelOverrides() as $provider => $model) {
            if ($model !== '') {
                config(["ai.providers.{$provider}.model" => $model]);
            }
        }

        foreach ($this->rawKeyMap() as $provider => $encrypted) {
            try {
                config(["ai.providers.{$provider}.api_key" => Crypt::decryptString($encrypted)]);
            } catch (DecryptException) {
                // Stale ciphertext (e.g. APP_KEY rotated) — keep the env key.
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function rawKeyMap(): array
    {
        $keys = PlatformSetting::getValue(self::KEY_PROVIDER_KEYS, []);

        return is_array($keys) ? array_filter($keys, 'is_string') : [];
    }

    private function settingsTableReady(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('platform_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
