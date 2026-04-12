<?php

declare(strict_types=1);

namespace App\Export;

use App\Entity\Community\Community;
use Waaseyaa\Access\AccountInterface;

interface ExportServiceInterface
{
    public function export(Community $community, AccountInterface $account): string;
}
