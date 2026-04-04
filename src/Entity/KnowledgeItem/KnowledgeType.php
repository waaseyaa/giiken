<?php

declare(strict_types=1);

namespace Giiken\Entity\KnowledgeItem;

enum KnowledgeType: string
{
    case Cultural     = 'cultural';
    case Governance   = 'governance';
    case Land         = 'land';
    case Relationship = 'relationship';
    case Event        = 'event';
}
