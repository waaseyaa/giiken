<?php

declare(strict_types=1);

namespace App\Access;

enum CommunityRole: string
{
    case Admin           = 'admin';
    case KnowledgeKeeper = 'knowledge_keeper';
    case Staff           = 'staff';
    case Member          = 'member';
    case Public          = 'public';

    public function rank(): int
    {
        return match ($this) {
            self::Admin           => 5,
            self::KnowledgeKeeper => 4,
            self::Staff           => 3,
            self::Member          => 2,
            self::Public          => 1,
        };
    }
}
