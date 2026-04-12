<?php

declare(strict_types=1);

namespace App\Query\Report;

final readonly class ReportResult
{
    public function __construct(
        public string $markdown,
        public int $includedItemCount,
    ) {}
}
