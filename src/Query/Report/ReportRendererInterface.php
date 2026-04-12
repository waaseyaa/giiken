<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Entity\Community\Community;
use App\Entity\KnowledgeItem\KnowledgeItem;

interface ReportRendererInterface
{
    /** @param KnowledgeItem[] $knowledgeItems */
    public function render(Community $community, array $knowledgeItems, DateRange $dateRange): string;

    public function getType(): string;
}
