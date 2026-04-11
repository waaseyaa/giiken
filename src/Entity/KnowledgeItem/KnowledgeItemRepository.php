<?php

declare(strict_types=1);

namespace Giiken\Entity\KnowledgeItem;

use Carbon\CarbonImmutable;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;
use Waaseyaa\Search\SearchIndexerInterface;

final class KnowledgeItemRepository implements KnowledgeItemRepositoryInterface
{
    public function __construct(
        private readonly EntityRepositoryInterface $repository,
        private readonly ?SearchIndexerInterface $indexer = null,
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
        $item->set('updated_at', CarbonImmutable::now()->toIso8601String());

        $wasNew = $item->get('id') === null || $item->get('id') === '';

        // EntityRepository::save() dispatches POST_SAVE, which the framework's
        // Waaseyaa\Search\EventSubscriber\SearchIndexSubscriber handles by
        // calling indexer->index($entity). On new rows the entity still has
        // id=null at that point (SqlStorageDriver does not back-fill the
        // auto-increment pk), so the subscriber writes a document_id of
        // "knowledge_item:" with an empty suffix and every new item clobbers
        // the last one. We work around it here: after save, scrub that stale
        // empty-suffix row and re-index the freshly-loaded entity that now
        // has its real id.
        $this->repository->save($item);

        if ($this->indexer === null) {
            return;
        }

        if ($wasNew) {
            $this->indexer->remove('knowledge_item:');

            $uuid = (string) ($item->get('uuid') ?? '');
            if ($uuid !== '') {
                $reloaded = $this->findByUuid($uuid);
                if ($reloaded !== null) {
                    $this->indexer->index($reloaded);

                    return;
                }
            }
        }

        $this->indexer->index($item);
    }

    private function findByUuid(string $uuid): ?KnowledgeItem
    {
        $results = $this->repository->findBy(['uuid' => $uuid], limit: 1);
        foreach ($results as $entity) {
            if ($entity instanceof KnowledgeItem) {
                return $entity;
            }
        }

        return null;
    }

    public function delete(KnowledgeItem $item): void
    {
        $this->repository->delete($item);
    }
}
