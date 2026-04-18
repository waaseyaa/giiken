<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccountInterface;

interface CommunityRoleResolverInterface
{
    public function resolve(string $communityId, AccountInterface $account): CommunityRole;
}
