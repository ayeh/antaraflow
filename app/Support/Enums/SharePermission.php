<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum SharePermission: string
{
    case View = 'view';
    case Comment = 'comment';
    case Edit = 'edit';
}
