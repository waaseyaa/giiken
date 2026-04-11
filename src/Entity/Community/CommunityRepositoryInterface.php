<?php

declare(strict_types=1);

namespace Giiken\Entity\Community;

interface CommunityRepositoryInterface
{
    public function find(string $id): ?Community;

    public function findBySlug(string $slug): ?Community;

    /**
     * Return every community in the table, name-sorted. The method intentionally
     * has no visibility filter: Giiken has no concept of private/draft
     * communities yet, so introducing one now would be speculative. When a
     * real visibility flag lands, either add a new filtered method alongside
     * this one or add a `visibility` parameter here. See waaseyaa/giiken#64.
     *
     * @return list<Community>
     */
    public function findAll(?int $limit = null): array;

    public function save(Community $community): void;

    public function delete(Community $community): void;
}
