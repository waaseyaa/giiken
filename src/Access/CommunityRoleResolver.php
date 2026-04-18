<?php

declare(strict_types=1);

namespace App\Access;

use Waaseyaa\Access\AccountInterface;

final class CommunityRoleResolver implements CommunityRoleResolverInterface
{
    public function resolve(string $communityId, AccountInterface $account): CommunityRole
    {
        $prefix = "giiken.community.{$communityId}.";

        foreach ($account->getRoles() as $role) {
            if (!str_starts_with($role, $prefix)) {
                continue;
            }

            $slug = substr($role, strlen($prefix));
            $communityRole = CommunityRole::tryFrom($slug);

            if ($communityRole !== null) {
                return $communityRole;
            }
        }

        return CommunityRole::Public;
    }
}
