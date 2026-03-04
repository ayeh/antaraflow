<?php

declare(strict_types=1);

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class BrandingService
{
    /** @var array<string, mixed> */
    private const array DEFAULTS = [
        'app_name' => 'antaraFLOW',
        'primary_color' => '#7c3aed',
        'secondary_color' => '#3b82f6',
        'accent_color' => '#10b981',
        'danger_color' => '#ef4444',
        'success_color' => '#22c55e',
        'footer_text' => '',
        'support_email' => '',
        'custom_css' => '',
        'custom_domain' => '',
        'logo_url' => '',
        'favicon_url' => '',
        'login_background_url' => '',
        'logo_path' => '',
        'favicon_path' => '',
        'login_background_path' => '',
        'email_header_html' => '',
        'email_footer_html' => '',
        'heading_font' => 'Inter',
        'body_font' => 'Inter',
        'custom_themes' => '[]',
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember(
            "branding.{$key}",
            now()->addHours(1),
            fn () => PlatformSetting::getValue($key, $default ?? (self::DEFAULTS[$key] ?? null)),
        );
    }

    public function appName(): string
    {
        return (string) $this->get('app_name', 'antaraFLOW');
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $result = [];

        foreach (self::DEFAULTS as $key => $default) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function clearCache(): void
    {
        foreach (array_keys(self::DEFAULTS) as $key) {
            Cache::forget("branding.{$key}");
        }
    }
}
