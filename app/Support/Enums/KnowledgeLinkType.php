<?php

declare(strict_types=1);

namespace App\Support\Enums;

enum KnowledgeLinkType: string
{
    case RelatedTo = 'related_to';
    case FollowsUp = 'follows_up';
    case Contradicts = 'contradicts';
    case Supersedes = 'supersedes';

    public function label(): string
    {
        return match ($this) {
            self::RelatedTo => __('Related To'),
            self::FollowsUp => __('Follows Up'),
            self::Contradicts => __('Contradicts'),
            self::Supersedes => __('Supersedes'),
        };
    }
}
