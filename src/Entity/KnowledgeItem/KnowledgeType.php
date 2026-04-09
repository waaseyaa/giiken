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
    /** Q&A or compiled synthesis saved back to the wiki (Phase 3). */
    case Synthesis = 'synthesis';
}
