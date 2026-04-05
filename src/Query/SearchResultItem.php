<?php

declare(strict_types=1);

namespace Giiken\Query;

use Giiken\Entity\KnowledgeItem\KnowledgeType;

final readonly class SearchResultItem
{
    public function __construct(
        public string $id,
        public string $title,
        public string $summary,
        public ?KnowledgeType $knowledgeType,
        public float $score,
    ) {}
}
