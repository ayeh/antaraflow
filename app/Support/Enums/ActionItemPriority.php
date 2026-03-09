<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ActionItemPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
            self::Critical => 'Critical',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Low => 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300',
            self::Medium => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            self::High => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
            self::Critical => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        };
    }
}
