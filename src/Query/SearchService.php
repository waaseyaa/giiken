<?php

declare(strict_types=1);

namespace App\Query;

use App\Access\KnowledgeItemAccessPolicy;
use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Pipeline\Provider\EmbeddingProviderInterface;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Search\SearchFilters;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\SearchRequest;

class SearchService
{
    private const SEMANTIC_WEIGHT = 0.6;
    private const FTS_WEIGHT      = 0.4;

    /**
     * Linear bonus added to a document's merged FTS raw score for every
     * query term it matched beyond the first. Rewards documents that match
     * more of the user's query without overwhelming the individual
     * per-term relevance signal. Tune here, not inline. See giiken#68.
     */
    private const MULTI_TERM_MATCH_BONUS = 0.05;

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
        $accessible = $this->applyQueryFilters($accessible, $query);

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
        // Waaseyaa\Search\Fts5SearchProvider::escapeQuery quotes each whitespace-
        // separated term individually, which FTS5 treats as an implicit AND. A
        // multi-word natural-language question like "what is governance about"
        // therefore requires every term to appear in the same row, and nothing
        // matches. We work around it by tokenizing the query ourselves (drop
        // stopwords and trivially short terms) and issuing one FTS search per
        // surviving term, then OR-ing the hits together by keeping each doc's
        // best per-term score. If the tokenizer throws everything away we fall
        // back to the original single-shot path so already-simple queries keep
        // working. See waaseyaa/giiken#61.
        $ftsRaw = $this->runFtsOverTerms($query->query, $query->locale);

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
        $accessible = $this->applyQueryFilters($accessible, $query);

        if ($accessible === []) {
            [$fallbackItems, $fallbackScores] = $this->fallbackSearchFromRepository($query, $account);
            if ($fallbackItems !== []) {
                $accessible = $fallbackItems;
                $scores = $fallbackScores;
            }
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
            fn (KnowledgeItem $item): SearchResultItem => new SearchResultItem(
                id: (string) ($item->get('id') ?? ''),
                title: $item->getTitle(),
                summary: $this->buildCardSummary($item),
                knowledgeType: $item->getKnowledgeType(),
                score: $scoreMap[(string) ($item->get('id') ?? '')] ?? 0.0,
                accessTier: $item->getAccessTier()->value,
                sourceOrigin: (string) ($item->get('source_origin_type') ?? 'manual'),
                createdAt: $item->getCreatedAt(),
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
     * Run FTS once per content-bearing term and merge the hits by taking each
     * document's best score across terms, then add a linear bonus proportional
     * to the number of distinct query terms each document matched. Returns a
     * map of entity-id => adjusted raw score suitable for feeding into the
     * existing min-max normalization path.
     *
     * @return array<string, float>
     */
    private function runFtsOverTerms(string $query, ?string $locale): array
    {
        $terms = $this->tokenizeForFts($query, $locale);
        if ($terms === []) {
            // Everything filtered out — hand the whole query to FTS as-is and
            // let the vendor escaper do its thing. Mirrors the pre-#61 path.
            $terms = [$query];
        }

        /** @var array<string, float> $bestScore */
        $bestScore = [];
        /** @var array<string, array<string, true>> $matchedTerms */
        $matchedTerms = [];
        foreach ($terms as $term) {
            $request = new SearchRequest(
                query: $term,
                filters: new SearchFilters(),
                page: 1,
                pageSize: 100,
            );
            $result = $this->ftsProvider->search($request);
            foreach ($result->hits as $hit) {
                $entityId = $this->parseEntityId($hit->id);
                if ($entityId === null) {
                    continue;
                }
                if (!isset($bestScore[$entityId]) || $hit->score > $bestScore[$entityId]) {
                    $bestScore[$entityId] = $hit->score;
                }
                $matchedTerms[$entityId][$term] = true;
            }
        }

        /** @var array<string, float> $raw */
        $raw = [];
        foreach ($bestScore as $entityId => $score) {
            $extraMatches = count($matchedTerms[$entityId] ?? []) - 1;
            $raw[$entityId] = $score + (self::MULTI_TERM_MATCH_BONUS * max(0, $extraMatches));
        }

        return $raw;
    }

    /**
     * Split a natural-language query into content-bearing terms. Lowercases
     * and strips punctuation. Applies English stopword filtering only when
     * locale is null (back-compat) or 'en' — other locales keep every
     * non-empty token so Indigenous-language queries are not silently
     * eroded. Length floor is 1, since FTS5 handles single-character tokens
     * fine and short stem words are meaningful in several Indigenous
     * languages (see waaseyaa/giiken#67).
     *
     * @return string[]
     */
    private function tokenizeForFts(string $query, ?string $locale): array
    {
        $applyEnglishStopwords = ($locale === null || $locale === 'en');
        $stopwords = $applyEnglishStopwords ? self::englishStopwords() : [];

        $rawTerms = preg_split('/[^\p{L}\p{N}]+/u', $query) ?: [];

        /** @var string[] $terms */
        $terms = [];
        $seen = [];
        foreach ($rawTerms as $term) {
            $lower = mb_strtolower($term);
            if ($lower === '') {
                continue;
            }
            if (isset($stopwords[$lower])) {
                continue;
            }
            if (isset($seen[$lower])) {
                continue;
            }
            $seen[$lower] = true;
            $terms[] = $lower;
        }

        return $terms;
    }

    /**
     * @return array<string, true>
     */
    private static function englishStopwords(): array
    {
        static $stopwords = [
            'a' => true, 'an' => true, 'the' => true,
            'is' => true, 'are' => true, 'am' => true, 'be' => true, 'was' => true, 'were' => true,
            'of' => true, 'in' => true, 'on' => true, 'at' => true, 'to' => true, 'for' => true,
            'and' => true, 'or' => true, 'not' => true, 'but' => true,
            'what' => true, 'when' => true, 'where' => true, 'why' => true, 'how' => true, 'who' => true,
            'this' => true, 'that' => true, 'these' => true, 'those' => true,
            'do' => true, 'does' => true, 'did' => true, 'done' => true,
            'about' => true, 'with' => true, 'as' => true, 'by' => true, 'from' => true,
            'i' => true, 'me' => true, 'my' => true, 'we' => true, 'us' => true, 'our' => true,
            'you' => true, 'your' => true, 'it' => true, 'its' => true,
            'can' => true, 'could' => true, 'should' => true, 'would' => true, 'may' => true, 'might' => true,
            'there' => true, 'here' => true,
        ];

        return $stopwords;
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

    /**
     * @param KnowledgeItem[] $items
     * @return KnowledgeItem[]
     */
    private function applyQueryFilters(array $items, SearchQuery $query): array
    {
        $filtered = $items;

        $typeFilter = trim((string) ($query->filters['knowledge_type'] ?? ''));
        if ($typeFilter !== '') {
            $filtered = array_values(array_filter(
                $filtered,
                static fn (KnowledgeItem $item): bool => $item->getKnowledgeType()?->value === $typeFilter,
            ));
        }

        $sagamokCurated = (string) ($query->filters['sagamok_curated'] ?? '') === '1';
        if ($sagamokCurated) {
            $filtered = $this->filterSagamokNoise($filtered);
        }

        return $filtered;
    }

    /**
     * Reduce obvious local-test noise for the Sagamok slice.
     *
     * @param KnowledgeItem[] $items
     * @return KnowledgeItem[]
     */
    private function filterSagamokNoise(array $items): array
    {
        $placeholderTitles = [
            'welcome to giiken' => true,
            'governance overview' => true,
            'land and territory' => true,
        ];
        $keywords = ['sagamok', 'anishnawbek', 'anishinabek nation', 'anishinaabe', 'robinson-huron', 'north shore'];

        $deduped = [];
        $seenNorthCloudTitles = [];
        foreach ($items as $item) {
            $title = trim($item->getTitle());
            $normalizedTitle = mb_strtolower($title);

            if (isset($placeholderTitles[$normalizedTitle])) {
                continue;
            }

            $origin = (string) ($item->get('source_origin_type') ?? 'manual');
            if ($origin === 'northcloud') {
                if (isset($seenNorthCloudTitles[$normalizedTitle])) {
                    continue;
                }

                $haystack = mb_strtolower(
                    $item->getTitle() . ' ' .
                    $item->getContent() . ' ' .
                    (string) ($item->get('source_reference_url') ?? ''),
                );

                $matches = false;
                foreach ($keywords as $keyword) {
                    if (str_contains($haystack, $keyword)) {
                        $matches = true;
                        break;
                    }
                }

                if (!$matches) {
                    continue;
                }

                $seenNorthCloudTitles[$normalizedTitle] = true;
            }

            $deduped[] = $item;
        }

        return $deduped;
    }

    /**
     * Safety net when the FTS index is stale/missing rows: run a lightweight
     * in-repository lexical match so discovery does not appear empty.
     *
     * @return array{0: KnowledgeItem[], 1: array<string, float>}
     */
    private function fallbackSearchFromRepository(SearchQuery $query, AccountInterface $account): array
    {
        $terms = $this->tokenizeForFts($query->query, $query->locale);
        if ($terms === []) {
            $raw = trim(mb_strtolower($query->query));
            if ($raw !== '') {
                $terms = [$raw];
            }
        }
        if ($terms === []) {
            return [[], []];
        }

        $all = $this->repository->findByCommunity($query->communityId);
        $candidates = $this->applyQueryFilters($this->filterAccessible($all, $account), $query);

        /** @var array<string, KnowledgeItem> $itemsById */
        $itemsById = [];
        /** @var array<string, float> $scoreMap */
        $scoreMap = [];
        foreach ($candidates as $item) {
            $id = (string) ($item->get('id') ?? '');
            if ($id === '') {
                continue;
            }

            $haystack = mb_strtolower($item->getTitle() . ' ' . $item->getContent());
            $matches = 0;
            foreach ($terms as $term) {
                if ($term !== '' && str_contains($haystack, $term)) {
                    $matches++;
                }
            }
            if ($matches === 0) {
                continue;
            }

            $itemsById[$id] = $item;
            $scoreMap[$id] = $matches / count($terms);
        }

        arsort($scoreMap);
        $orderedItems = [];
        foreach (array_keys($scoreMap) as $id) {
            $orderedItems[] = $itemsById[$id];
        }

        return [$orderedItems, $scoreMap];
    }

    private function buildCardSummary(KnowledgeItem $item): string
    {
        $content = trim($item->getContent());
        if ($content === '') {
            return 'No summary available.';
        }

        $clean = $this->stripMarkdownNoise($content);
        $title = trim($item->getTitle());
        $normalizedTitle = $title !== '' ? $this->stripMarkdownNoise($title) : '';
        if ($normalizedTitle !== '' && mb_stripos($clean, $normalizedTitle) === 0) {
            $clean = trim((string) mb_substr($clean, mb_strlen($normalizedTitle)));
            $clean = ltrim($clean, ":-| \t\n\r\0\x0B");
        }

        if ($clean === '') {
            $clean = $this->stripMarkdownNoise($title . ' ' . $content);
        }
        if ($clean === '') {
            return 'No summary available.';
        }

        $limit = 220;
        if (mb_strlen($clean) <= $limit) {
            return $clean;
        }

        return rtrim((string) mb_substr($clean, 0, $limit - 1)) . '…';
    }

    private function stripMarkdownNoise(string $text): string
    {
        $plain = str_replace(["\r\n", "\r"], "\n", $text);
        $plain = preg_replace('/```.*?```/su', ' ', $plain) ?? $plain;
        $plain = preg_replace('/`([^`]+)`/u', '$1', $plain) ?? $plain;
        $plain = preg_replace('/\[(.*?)\]\((.*?)\)/u', '$1', $plain) ?? $plain;
        $plain = preg_replace('/^\s{0,3}#{1,6}\s*/mu', '', $plain) ?? $plain;
        $plain = preg_replace('/^\s*[-*+]\s+/mu', '', $plain) ?? $plain;
        $plain = preg_replace('/^\s*\d+\.\s+/mu', '', $plain) ?? $plain;
        $plain = preg_replace('/[*_]{1,3}([^*_]+)[*_]{1,3}/u', '$1', $plain) ?? $plain;
        $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

        return trim($plain);
    }
}
