<?php

declare(strict_types=1);

namespace App\Pipeline\Provider;

interface EmbeddingProviderInterface
{
    /** @return float[] */
    public function embed(string $text): array;

    /** @return array<array{id: string, score: float}> */
    public function search(string $query, string $communityId, int $limit = 5): array;

    public function store(string $entityId, string $text, string $communityId): void;
}
