<?php

declare(strict_types=1);

namespace Giiken\Entity\KnowledgeItem;

use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class KnowledgeItemRepository
{
    public function __construct(
        private readonly EntityRepositoryInterface $repository,
    ) {}

    public function find(string $id): ?KnowledgeItem
    {
        $entity = $this->repository->find($id);

        return $entity instanceof KnowledgeItem ? $entity : null;
    }

    /**
     * @return KnowledgeItem[]
     */
    public function findByCommunity(string $communityId): array
    {
        $results = $this->repository->findBy(['community_id' => $communityId]);

        return array_values(array_filter(
            $results,
            static fn (mixed $e): bool => $e instanceof KnowledgeItem,
        ));
    }

    public function save(KnowledgeItem $item): void
    {
        $item->set('updated_at', date('c'));
        $this->repository->save($item);
    }

    public function delete(KnowledgeItem $item): void
    {
        $this->repository->delete($item);
    }
}
