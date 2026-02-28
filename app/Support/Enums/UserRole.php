<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';
    case Viewer = 'viewer';
}
