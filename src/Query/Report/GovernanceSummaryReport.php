<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Entity\Community\Community;
use App\Entity\KnowledgeItem\KnowledgeItem;

final class GovernanceSummaryReport implements ReportRendererInterface
{
    public function getType(): string
    {
        return 'governance_summary';
    }

    /** @param KnowledgeItem[] $knowledgeItems */
    public function render(Community $community, array $knowledgeItems, DateRange $dateRange): string
    {
        $lines = [];
        $lines[] = '# Governance Summary: ' . $community->name();
        $lines[] = '';
        $lines[] = '**Period:** ' . $dateRange->from->format('Y-m-d') . ' to ' . $dateRange->to->format('Y-m-d');
        $lines[] = '';

        if ($knowledgeItems === []) {
            $lines[] = 'No governance items found for this period.';

            return implode("\n", $lines);
        }

        $count = count($knowledgeItems);
        $lines[] = "**Summary:** {$count} governance item(s) in this period.";
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
