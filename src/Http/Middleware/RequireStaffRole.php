<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Access\CommunityRole;
use App\Access\CommunityRoleResolver;
use App\Access\CommunityRoleResolverInterface;
use Waaseyaa\Access\AccountInterface;

final class RequireStaffRole
{
    private const MINIMUM_RANK = 3; // Staff

    public function __construct(
        private readonly ?CommunityRoleResolverInterface $roleResolver = null,
    ) {}

    public function check(?AccountInterface $account, string $communityId): bool
    {
        if ($account === null) {
            return false;
        }

        $role = ($this->roleResolver ?? new CommunityRoleResolver())->resolve($communityId, $account);

        return $role->rank() >= self::MINIMUM_RANK;
    }
}
