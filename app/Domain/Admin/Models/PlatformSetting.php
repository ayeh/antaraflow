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

        if (! $setting || $setting->value === null) {
            return $default;
        }

        return json_decode($setting->value, true);
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($value)],
        );
    }
}
