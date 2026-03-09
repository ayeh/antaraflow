<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum ActionItemStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case CarriedForward = 'carried_forward';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::CarriedForward => 'Carried Forward',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
            self::InProgress => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300',
            self::Completed => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300',
            self::Cancelled => 'bg-gray-100 text-gray-700 dark:bg-gray-900/30 dark:text-gray-300',
            self::CarriedForward => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
        };
    }
}
