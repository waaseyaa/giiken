<?php

declare(strict_types=1);

namespace App\Query;

use App\Entity\KnowledgeItem\KnowledgeType;

final readonly class SearchResultItem
{
    public function __construct(
        public string $id,
        public string $title,
        public string $summary,
        public ?KnowledgeType $knowledgeType,
        public float $score,
        public string $accessTier = 'members',
        public string $sourceOrigin = 'manual',
        public string $createdAt = '',
    ) {}
}
