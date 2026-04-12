<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem;

enum AccessTier: string
{
    case Public     = 'public';
    case Members    = 'members';
    case Staff      = 'staff';
    case Restricted = 'restricted';
}
