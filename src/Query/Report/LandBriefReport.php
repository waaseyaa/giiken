<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Entity\Community\Community;
use App\Entity\KnowledgeItem\KnowledgeItem;

final class LandBriefReport implements ReportRendererInterface
{
    public function getType(): string
    {
        return 'land_brief';
    }

    /** @param KnowledgeItem[] $knowledgeItems */
    public function render(Community $community, array $knowledgeItems, DateRange $dateRange): string
    {
        $lines = [];
        $lines[] = '# Land Brief: ' . $community->name();
        $lines[] = '';
        $lines[] = '**Period:** ' . $dateRange->from->format('Y-m-d') . ' to ' . $dateRange->to->format('Y-m-d');
        $lines[] = '';

        if ($knowledgeItems === []) {
            $lines[] = 'No land items found for this period.';

            return implode("\n", $lines);
        }

        $count = count($knowledgeItems);
        $lines[] = "**Summary:** {$count} land item(s) in this period.";
        $lines[] = '';
        $lines[] = '---';

        foreach ($knowledgeItems as $item) {
            $lines[] = '';
            $lines[] = '## ' . $item->getTitle();
            $lines[] = '';
            $lines[] = $item->getContent();
        }

        return implode("\n", $lines);
    }
}
