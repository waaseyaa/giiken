<?php

declare(strict_types=1);

namespace Giiken\Query\Report;

use Giiken\Entity\Community\Community;
use Waaseyaa\Access\AccountInterface;

interface ReportServiceInterface
{
    public function generate(
        string $reportType,
        Community $community,
        DateRange $dateRange,
        AccountInterface $account,
    ): string;
}
