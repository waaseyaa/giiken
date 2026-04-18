<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem;

final class KnowledgeItemMarkdownPresenter
{
    public function render(KnowledgeItem $item): string
    {
        $lines = [];
        $lines[] = "# {$item->getTitle()}";
        $lines[] = '';

        $metaParts = [];

        $knowledgeType = $item->getKnowledgeType();
        if ($knowledgeType !== null) {
            $metaParts[] = '**Type:** ' . ucfirst($knowledgeType->value);
        }

        $metaParts[] = '**Access:** ' . ucfirst($item->getAccessTier()->value);

        $compiledAt = $item->getCompiledAt();
        if ($compiledAt !== '') {
            $metaParts[] = '**Compiled:** ' . $compiledAt;
        }

        $lines[] = implode(' | ', $metaParts);
        $lines[] = '';
        $lines[] = $item->getContent();

        $sourceMediaIds = $item->getSourceMediaIds();
        if ($sourceMediaIds !== []) {
            $lines[] = '';
            $lines[] = '---';
            $lines[] = 'Sources: ' . implode(', ', $sourceMediaIds);
        }

        return implode("\n", $lines);
    }
}
