<?php

declare(strict_types=1);

namespace App\Query;

final readonly class SearchResultSet
{
    /**
     * @param SearchResultItem[] $items
     */
    public function __construct(
        public array $items,
        public int $totalHits,
        public int $totalPages,
    ) {}

    public static function empty(): self
    {
        return new self(items: [], totalHits: 0, totalPages: 0);
    }
}
