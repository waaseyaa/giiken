<?php

declare(strict_types=1);

namespace Giiken\Export;

use Giiken\Entity\Community\Community;
use Waaseyaa\Access\AccountInterface;

interface ExportServiceInterface
{
    public function export(Community $community, AccountInterface $account): string;
}
