<?php

declare(strict_types=1);

namespace App\Access;

use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeItem;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * Access policy for KnowledgeItem entities.
 *
 * Community roles are encoded as account roles in the format:
 *   giiken.community.{communityId}.{roleSlug}
 *
 * Example: giiken.community.abc-123.staff
 *
 * This allows multi-tenancy enforcement: a role granted in community A
 * carries no weight in community B.
 */
#[PolicyAttribute('knowledge_item')]
final class KnowledgeItemAccessPolicy implements AccessPolicyInterface
{
    public function appliesTo(string $entityTypeId): bool
    {
        return $entityTypeId === 'knowledge_item';
    }

    public function access(EntityInterface $entity, string $operation, AccountInterface $account): AccessResult
    {
        if (!$entity instanceof KnowledgeItem) {
            return AccessResult::neutral();
        }

        $role = $this->resolveRole($entity->getCommunityId(), $account);

        // Admins always have access.
        if ($role === CommunityRole::Admin) {
            return AccessResult::allowed('admin role');
        }

        return match ($entity->getAccessTier()) {
            AccessTier::Public     => AccessResult::allowed('public tier'),
            AccessTier::Members    => $role->rank() >= CommunityRole::Member->rank()
                ? AccessResult::allowed('member tier')
                : AccessResult::forbidden('member tier requires authentication'),
            AccessTier::Staff      => $role->rank() >= CommunityRole::Staff->rank()
                ? AccessResult::allowed('staff tier')
                : AccessResult::forbidden('staff tier requires staff role or above'),
            AccessTier::Restricted => $this->evaluateRestricted($role, $entity, $account),
        };
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        return AccessResult::neutral();
    }

    /**
     * Resolve the current account's community role for a specific community.
     *
     * Looks for a role string matching "giiken.community.{communityId}.{roleSlug}".
     * Falls back to CommunityRole::Public if no match is found.
     */
    private function resolveRole(string $communityId, AccountInterface $account): CommunityRole
    {
        $prefix = "giiken.community.{$communityId}.";

        foreach ($account->getRoles() as $roleStr) {
            if (!str_starts_with($roleStr, $prefix)) {
                continue;
            }

            $slug = substr($roleStr, strlen($prefix));
            $communityRole = CommunityRole::tryFrom($slug);

            if ($communityRole !== null) {
                return $communityRole;
            }
        }

        return CommunityRole::Public;
    }

    /**
     * Evaluate access for restricted-tier items.
     *
     * Knowledge Keepers and above are always allowed.
     * Below that, access is granted if the user's role slug is in allowed_roles
     * OR the user's ID is in allowed_users.
     */
    private function evaluateRestricted(
        CommunityRole $role,
        KnowledgeItem $entity,
        AccountInterface $account,
    ): AccessResult {
        if ($role->rank() >= CommunityRole::KnowledgeKeeper->rank()) {
            return AccessResult::allowed('knowledge_keeper role');
        }

        if (in_array($role->value, $entity->getAllowedRoles(), true)) {
            return AccessResult::allowed('role in allowed_roles');
        }

        $userId = (string) $account->id();

        if ($userId !== '' && $userId !== '0' && in_array($userId, $entity->getAllowedUsers(), true)) {
            return AccessResult::allowed('user in allowed_users');
        }

        return AccessResult::forbidden('restricted tier: not in allowed_roles or allowed_users');
    }
}
