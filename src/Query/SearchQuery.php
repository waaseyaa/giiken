<?php

declare(strict_types=1);

namespace Giiken\Query;

final readonly class SearchQuery
{
    /**
     * @param array<string, string> $filters
     */
    public function __construct(
        public string $query,
        public string $communityId,
        public array $filters = [],
        public int $page = 1,
        public int $pageSize = 20,
    ) {}
}
