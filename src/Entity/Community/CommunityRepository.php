<?php

declare(strict_types=1);

namespace Giiken\Entity\Community;

use Carbon\CarbonImmutable;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class CommunityRepository implements CommunityRepositoryInterface
{
    public function __construct(
        private readonly EntityRepositoryInterface $repository,
    ) {}

    public function find(string $id): ?Community
    {
        $entity = $this->repository->find($id);

        return $entity instanceof Community ? $entity : null;
    }

    public function findBySlug(string $slug): ?Community
    {
        $results = $this->repository->findBy(['slug' => $slug]);
        $entity  = reset($results);

        return $entity instanceof Community ? $entity : null;
    }

    public function findAllPublic(?int $limit = null): array
    {
        $results = $this->repository->findBy([], ['name' => 'ASC'], $limit);

        return array_values(array_filter(
            $results,
            static fn ($entity): bool => $entity instanceof Community,
        ));
    }

    public function save(Community $community): void
    {
        $community->set('updated_at', CarbonImmutable::now()->toIso8601String());

        $this->repository->save($community);
    }

    public function delete(Community $community): void
    {
        $this->repository->delete($community);
    }
}
