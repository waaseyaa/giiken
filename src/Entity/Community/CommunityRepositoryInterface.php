<?php

declare(strict_types=1);

namespace Giiken\Entity\Community;

interface CommunityRepositoryInterface
{
    public function find(string $id): ?Community;

    public function findBySlug(string $slug): ?Community;

    public function save(Community $community): void;

    public function delete(Community $community): void;
}
