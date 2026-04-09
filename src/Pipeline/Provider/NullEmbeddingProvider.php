<?php

declare(strict_types=1);

namespace Giiken\Pipeline\Provider;

/**
 * Dev-friendly embedding + semantic search no-op (vector search returns no hits).
 */
final class NullEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        return [];
    }

    public function search(string $query, string $communityId, int $limit = 5): array
    {
        return [];
    }

    public function store(string $entityId, string $text, string $communityId): void {}
}
