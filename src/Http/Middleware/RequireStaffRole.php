<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Access\CommunityRole;
use Waaseyaa\Access\AccountInterface;

final class RequireStaffRole
{
    private const MINIMUM_RANK = 3; // Staff

    public function check(?AccountInterface $account, string $communityId): bool
    {
        if ($account === null) {
            return false;
        }

        $prefix = "giiken.community.{$communityId}.";

        foreach ($account->getRoles() as $role) {
            if (!str_starts_with($role, $prefix)) {
                continue;
            }

            $roleSlug      = substr($role, strlen($prefix));
            $communityRole = CommunityRole::tryFrom($roleSlug);

            if ($communityRole !== null && $communityRole->rank() >= self::MINIMUM_RANK) {
                return true;
            }
        }

        return false;
    }
}
