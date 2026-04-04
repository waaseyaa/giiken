<?php

declare(strict_types=1);

namespace Giiken\Access;

use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Waaseyaa\Access\AccessPolicyInterface;
use Waaseyaa\Access\AccessResult;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Access\Gate\PolicyAttribute;
use Waaseyaa\Entity\EntityInterface;

/**
 * MVP access policy: public view, open ingestion, admin-only delete.
 *
 * This policy is designed for the public-facing Massey Solar scenario (#27)
 * where anyone can view and add content, but only admins can delete.
 */
#[PolicyAttribute('knowledge_item')]
final class PublicIngestionPolicy implements AccessPolicyInterface
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

        if ($operation === 'delete') {
            return $this->evaluateDelete($entity, $account);
        }

        return AccessResult::allowed('public access');
    }

    public function createAccess(string $entityTypeId, string $bundle, AccountInterface $account): AccessResult
    {
        return AccessResult::allowed('open ingestion');
    }

    private function evaluateDelete(KnowledgeItem $entity, AccountInterface $account): AccessResult
    {
        $communityId = $entity->getCommunityId();
        $prefix = "giiken.community.{$communityId}.";

        foreach ($account->getRoles() as $roleStr) {
            if (!str_starts_with($roleStr, $prefix)) {
                continue;
            }

            $slug = substr($roleStr, strlen($prefix));

            if (CommunityRole::tryFrom($slug) === CommunityRole::Admin) {
                return AccessResult::allowed('admin delete');
            }
        }

        return AccessResult::forbidden('only admins can delete');
    }
}
