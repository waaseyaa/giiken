<?php

declare(strict_types=1);

namespace App\Query;

use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeItem;

/**
 * Derives access metadata for a synthesis item from cited knowledge items.
 * Tier is the strictest among citations; restricted rows intersect allow-lists.
 */
final class SynthesisAccessCapper
{
    /**
     * @param KnowledgeItem[] $cited
     *
     * @return array{access_tier: string, allowed_roles: string[], allowed_users: string[]}
     */
    public static function cap(array $cited): array
    {
        if ($cited === []) {
            return [
                'access_tier'   => AccessTier::Members->value,
                'allowed_roles' => [],
                'allowed_users' => [],
            ];
        }

        $tier = AccessTier::Public;
        foreach ($cited as $item) {
            $tier = self::stricter($tier, $item->getAccessTier());
        }

        $allowedRoles = [];
        $allowedUsers = [];
        $restricted   = array_values(array_filter(
            $cited,
            static fn (KnowledgeItem $i): bool => $i->getAccessTier() === AccessTier::Restricted,
        ));

        if ($tier === AccessTier::Restricted && $restricted !== []) {
            $allowedRoles = self::intersectStringLists(array_map(
                static fn (KnowledgeItem $i): array => $i->getAllowedRoles(),
                $restricted,
            ));
            $allowedUsers = self::intersectStringLists(array_map(
                static fn (KnowledgeItem $i): array => $i->getAllowedUsers(),
                $restricted,
            ));
        }

        return [
            'access_tier'   => $tier->value,
            'allowed_roles' => $allowedRoles,
            'allowed_users' => $allowedUsers,
        ];
    }

    private static function stricter(AccessTier $a, AccessTier $b): AccessTier
    {
        return self::strictness($a) >= self::strictness($b) ? $a : $b;
    }

    private static function strictness(AccessTier $t): int
    {
        return match ($t) {
            AccessTier::Public     => 1,
            AccessTier::Members    => 2,
            AccessTier::Staff      => 3,
            AccessTier::Restricted => 4,
        };
    }

    /**
     * @param list<array<int, string>> $lists
     *
     * @return string[]
     */
    private static function intersectStringLists(array $lists): array
    {
        if ($lists === []) {
            return [];
        }

        $first = array_shift($lists);
        if ($first === []) {
            return [];
        }

        $set = array_flip($first);
        foreach ($lists as $list) {
            if ($list === []) {
                return [];
            }
            $set = array_intersect_key($set, array_flip($list));
        }

        return array_keys($set);
    }
}
