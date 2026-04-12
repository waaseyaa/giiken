<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Entity\Community\Community;
use Waaseyaa\Access\AccountInterface;

interface ReportServiceInterface
{
    public function generate(
        string $reportType,
        Community $community,
        DateRange $dateRange,
        AccountInterface $account,
    ): string;

    public function generateFromRequest(
        Community $community,
        ReportRequest $request,
        AccountInterface $account,
    ): ReportResult;
}
