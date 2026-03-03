<?php

declare(strict_types=1);

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $guarded = ['id'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $decoded = json_decode($setting->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $setting->value;
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) || is_object($value) ? json_encode($value) : (string) $value],
        );
    }
}
