<?php

declare(strict_types=1);

namespace Giiken\Query\Report;

use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;

interface ReportRendererInterface
{
    /** @param KnowledgeItem[] $knowledgeItems */
    public function render(Community $community, array $knowledgeItems, DateRange $dateRange): string;

    public function getType(): string;
}
