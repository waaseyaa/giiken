<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem;

final class KnowledgeItemSearchDocumentFactory
{
    /**
     * @return array<string, string>
     */
    public function make(KnowledgeItem $item): array
    {
        return [
            'title' => $item->getTitle(),
            'body' => $item->getContent(),
        ];
    }
}
