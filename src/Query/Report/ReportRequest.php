<?php

declare(strict_types=1);

namespace Giiken\Query\Report;

/**
 * @param string[] $knowledgeTypeValues Empty = use each report type's default filter only.
 */
final readonly class ReportRequest
{
    /**
     * @param string[] $knowledgeTypeValues
     */
    public function __construct(
        public string $reportType,
        public string $dateFromIso,
        public string $dateToIso,
        public array $knowledgeTypeValues = [],
    ) {}
}
