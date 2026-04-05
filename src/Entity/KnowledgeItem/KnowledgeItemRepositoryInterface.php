<?php

declare(strict_types=1);

namespace Giiken\Entity\KnowledgeItem;

interface KnowledgeItemRepositoryInterface
{
    public function find(string $id): ?KnowledgeItem;

    /** @return KnowledgeItem[] */
    public function findByCommunity(string $communityId): array;

    public function save(KnowledgeItem $item): void;

    public function delete(KnowledgeItem $item): void;
}
