<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum BookmarkKind: string
{
    case Decision = 'decision';
    case Action = 'action';
    case Question = 'question';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Decision => __('Decision'),
            self::Action => __('Action'),
            self::Question => __('Question'),
            self::General => __('General'),
        };
    }
}
