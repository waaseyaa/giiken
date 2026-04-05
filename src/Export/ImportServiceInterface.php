<?php

declare(strict_types=1);

namespace Giiken\Export;

use Waaseyaa\Access\AccountInterface;

interface ImportServiceInterface
{
    public function import(string $archivePath, AccountInterface $account): ImportResult;
}
