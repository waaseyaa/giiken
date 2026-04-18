<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem;

final class KnowledgeItemSearchMetadataFactory
{
    /**
     * @return array<string, mixed>
     */
    public function make(KnowledgeItem $item): array
    {
        return [
            'entity_type' => 'knowledge_item',
            'community_id' => $item->getCommunityId(),
            'knowledge_type' => $item->getKnowledgeType()?->value ?? '',
            'access_tier' => $item->getAccessTier()->value,
        ];
    }
}
