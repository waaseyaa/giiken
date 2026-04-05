<?php

declare(strict_types=1);

namespace Giiken\Query;

use Waaseyaa\Access\AccountInterface;

interface QaServiceInterface
{
    public function ask(string $question, string $communityId, ?AccountInterface $account): QaResponse;
}
