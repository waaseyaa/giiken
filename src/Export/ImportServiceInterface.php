<?php

declare(strict_types=1);

namespace App\Export;

use Waaseyaa\Access\AccountInterface;

interface ImportServiceInterface
{
    public function import(string $archivePath, AccountInterface $account): ImportResult;
}
