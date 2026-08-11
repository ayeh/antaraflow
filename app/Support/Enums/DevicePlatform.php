<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum DevicePlatform: string
{
    case Ios = 'ios';
    case Android = 'android';

    public function label(): string
    {
        return match ($this) {
            self::Ios => __('iOS'),
            self::Android => __('Android'),
        };
    }
}
