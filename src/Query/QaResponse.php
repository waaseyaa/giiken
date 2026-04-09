<?php

declare(strict_types=1);

namespace Giiken\Query;

final readonly class QaResponse
{
    /**
     * @param string[]       $citedItemIds
     * @param QaCitation[] $citations
     */
    public function __construct(
        public string $answer,
        public array $citedItemIds,
        public bool $noRelevantItems,
        public array $citations = [],
    ) {}
}
