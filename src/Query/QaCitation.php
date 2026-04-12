<?php

declare(strict_types=1);

namespace App\Query;

final readonly class QaCitation
{
    public function __construct(
        public string $itemId,
        public string $title,
        public string $excerpt,
        public ?string $knowledgeType = null,
    ) {}
}
