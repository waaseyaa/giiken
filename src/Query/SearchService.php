<?php

declare(strict_types=1);

namespace Giiken\Query;

use Giiken\Access\KnowledgeItemAccessPolicy;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Search\SearchFilters;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\SearchRequest;

class SearchService
{
    private const SEMANTIC_WEIGHT = 0.6;
    private const FTS_WEIGHT      = 0.4;

    public function __construct(
        private readonly SearchProviderInterface $ftsProvider,
        private readonly EmbeddingProviderInterface $embeddingProvider,
        private readonly KnowledgeItemAccessPolicy $accessPolicy,
        private readonly KnowledgeItemRepositoryInterface $repository,
    ) {}

    public function search(SearchQuery $query, ?AccountInterface $account): SearchResultSet
    {
        $account = $account ?? $this->anonymousAccount();

        if (trim($query->query) === '') {
            return $this->recentItems($query, $account);
        }

        return $this->hybridSearch($query, $account);
    }

    // ------------------------------------------------------------------
    // Empty-query path
    // ------------------------------------------------------------------

    private function recentItems(SearchQuery $query, AccountInterface $account): SearchResultSet
    {
        $all = $this->repository->findByCommunity($query->communityId);

        usort($all, static fn (KnowledgeItem $a, KnowledgeItem $b): int =>
            strcmp($b->getCreatedAt(), $a->getCreatedAt())
        );

        $accessible = $this->filterAccessible($all, $account);

        return $this->paginate(
            items: $accessible,
            page: $query->page,
            pageSize: $query->pageSize,
            scoreMap: [],
        );
    }

    // ------------------------------------------------------------------
    // Hybrid-search path
    // ------------------------------------------------------------------

    private function hybridSearch(SearchQuery $query, AccountInterface $account): SearchResultSet
    {
        // --- FTS ---
        $ftsRequest = new SearchRequest(
            query: $query->query,
            filters: new SearchFilters(),
            page: 1,
            pageSize: 100,
        );
        $ftsResult = $this->ftsProvider->search($ftsRequest);

        /** @var array<string, float> $ftsRaw id => raw score */
        $ftsRaw = [];
        foreach ($ftsResult->hits as $hit) {
            $entityId = $this->parseEntityId($hit->id);
            if ($entityId === null) {
                continue;
            }
            // Filter to this community post-query (SearchFilters has no community_id).
            $ftsRaw[$entityId] = $hit->score;
        }

        // --- Semantic ---
        $semanticRaw = [];
        foreach ($this->embeddingProvider->search($query->query, $query->communityId, 100) as $entry) {
            $semanticRaw[$entry['id']] = $entry['score'];
        }

        // --- Normalize each set to 0-1 (min-max) ---
        $ftsNorm      = $this->minMaxNormalize($ftsRaw);
        $semanticNorm = $this->minMaxNormalize($semanticRaw);

        // --- Merge with weights ---
        $allIds = array_unique(array_merge(array_keys($ftsNorm), array_keys($semanticNorm)));

        /** @var array<string, float> $scores */
        $scores = [];
        foreach ($allIds as $id) {
            $fts      = $ftsNorm[$id]      ?? null;
            $semantic = $semanticNorm[$id] ?? null;

            if ($fts !== null && $semantic !== null) {
                $scores[$id] = self::SEMANTIC_WEIGHT * $semantic + self::FTS_WEIGHT * $fts;
            } elseif ($semantic !== null) {
                $scores[$id] = self::SEMANTIC_WEIGHT * $semantic;
            } else {
                $scores[$id] = self::FTS_WEIGHT * ($fts ?? 0.0);
            }
        }

        arsort($scores);

        // --- Load entities, filter by community and access ---
        /** @var KnowledgeItem[] $accessible */
        $accessible = [];
        foreach (array_keys($scores) as $id) {
            // PHP casts numeric-string array keys to int, so $id may be int|string here.
            $item = $this->repository->find((string) $id);
            if ($item === null) {
                continue;
            }
            if ($item->getCommunityId() !== $query->communityId) {
                continue;
            }
            if (!$this->accessPolicy->access($item, 'view', $account)->isAllowed()) {
                continue;
            }
            $accessible[] = $item;
        }

        return $this->paginate(
            items: $accessible,
            page: $query->page,
            pageSize: $query->pageSize,
            scoreMap: $scores,
        );
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param KnowledgeItem[] $items
     * @param array<string, float> $scoreMap  id => merged score (empty for recent-items path)
     */
    private function paginate(array $items, int $page, int $pageSize, array $scoreMap): SearchResultSet
    {
        $total      = count($items);
        $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 0;
        $offset     = ($page - 1) * $pageSize;
        $slice      = array_slice($items, $offset, $pageSize);

        $resultItems = array_map(
            static fn (KnowledgeItem $item): SearchResultItem => new SearchResultItem(
                id: (string) ($item->get('id') ?? ''),
                title: $item->getTitle(),
                summary: $item->getContent(),
                knowledgeType: $item->getKnowledgeType(),
                score: $scoreMap[(string) ($item->get('id') ?? '')] ?? 0.0,
            ),
            $slice,
        );

        return new SearchResultSet(
            items: $resultItems,
            totalHits: $total,
            totalPages: $totalPages,
        );
    }

    /**
     * @param KnowledgeItem[] $items
     * @return KnowledgeItem[]
     */
    private function filterAccessible(array $items, AccountInterface $account): array
    {
        return array_values(array_filter(
            $items,
            fn (KnowledgeItem $item): bool =>
                $this->accessPolicy->access($item, 'view', $account)->isAllowed(),
        ));
    }

    /**
     * @param array<string, float> $scores
     * @return array<string, float>
     */
    private function minMaxNormalize(array $scores): array
    {
        if ($scores === []) {
            return [];
        }

        $min = min($scores);
        $max = max($scores);

        if ($max === $min) {
            return array_map(static fn (): float => 1.0, $scores);
        }

        $range = $max - $min;

        return array_map(
            static fn (float $s): float => ($s - $min) / $range,
            $scores,
        );
    }

    private function parseEntityId(string $hitId): ?string
    {
        if (!str_starts_with($hitId, 'knowledge_item:')) {
            return null;
        }

        return substr($hitId, strlen('knowledge_item:'));
    }

    private function anonymousAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int|string { return '0'; }
            public function getRoles(): array { return []; }
            public function isAuthenticated(): bool { return false; }
            public function hasPermission(string $permission): bool { return false; }
        };
    }
}
