<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Query;

use Giiken\Access\KnowledgeItemAccessPolicy;
use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use Giiken\Query\SearchQuery;
use Giiken\Query\SearchService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Search\SearchFilters;
use Waaseyaa\Search\SearchHit;
use Waaseyaa\Search\SearchProviderInterface;
use Waaseyaa\Search\SearchRequest;
use Waaseyaa\Search\SearchResult;

#[CoversClass(SearchService::class)]
final class SearchServiceTest extends TestCase
{
    private const COMMUNITY = 'comm-1';

    private SearchProviderInterface&MockObject $ftsProvider;
    private EmbeddingProviderInterface&MockObject $embeddingProvider;
    private KnowledgeItemRepositoryInterface&MockObject $repository;
    private KnowledgeItemAccessPolicy $accessPolicy;
    private SearchService $service;

    protected function setUp(): void
    {
        $this->ftsProvider       = $this->createMock(SearchProviderInterface::class);
        $this->embeddingProvider = $this->createMock(EmbeddingProviderInterface::class);
        $this->repository        = $this->createMock(KnowledgeItemRepositoryInterface::class);
        $this->accessPolicy      = new KnowledgeItemAccessPolicy();

        $this->service = new SearchService(
            ftsProvider: $this->ftsProvider,
            embeddingProvider: $this->embeddingProvider,
            accessPolicy: $this->accessPolicy,
            repository: $this->repository,
        );
    }

    // ------------------------------------------------------------------
    // Empty query fallback
    // ------------------------------------------------------------------

    #[Test]
    public function empty_query_returns_recent_items_without_calling_search_providers(): void
    {
        $items = [
            $this->item('item-1', AccessTier::Public, '2026-01-03T00:00:00+00:00'),
            $this->item('item-2', AccessTier::Public, '2026-01-01T00:00:00+00:00'),
            $this->item('item-3', AccessTier::Public, '2026-01-02T00:00:00+00:00'),
        ];

        $this->repository
            ->expects($this->once())
            ->method('findByCommunity')
            ->with(self::COMMUNITY)
            ->willReturn($items);

        $this->ftsProvider->expects($this->never())->method('search');
        $this->embeddingProvider->expects($this->never())->method('search');

        $query  = new SearchQuery(query: '', communityId: self::COMMUNITY, page: 1, pageSize: 20);
        $result = $this->service->search($query, $this->memberAccount());

        $this->assertCount(3, $result->items);
        // Sorted by created_at desc: item-1, item-3, item-2
        $this->assertSame('item-1', $result->items[0]->id);
        $this->assertSame('item-3', $result->items[1]->id);
        $this->assertSame('item-2', $result->items[2]->id);
    }

    #[Test]
    public function empty_query_filters_inaccessible_items(): void
    {
        $items = [
            $this->item('pub-1', AccessTier::Public, '2026-01-01T00:00:00+00:00'),
            $this->item('staff-1', AccessTier::Staff, '2026-01-02T00:00:00+00:00'),
        ];

        $this->repository
            ->method('findByCommunity')
            ->willReturn($items);

        $query  = new SearchQuery(query: '', communityId: self::COMMUNITY, page: 1, pageSize: 20);
        $result = $this->service->search($query, $this->memberAccount());

        $this->assertCount(1, $result->items);
        $this->assertSame('pub-1', $result->items[0]->id);
    }

    #[Test]
    public function empty_query_with_null_account_returns_only_public_items(): void
    {
        $items = [
            $this->item('pub-1', AccessTier::Public, '2026-01-01T00:00:00+00:00'),
            $this->item('mem-1', AccessTier::Members, '2026-01-02T00:00:00+00:00'),
        ];

        $this->repository
            ->method('findByCommunity')
            ->willReturn($items);

        $query  = new SearchQuery(query: '', communityId: self::COMMUNITY);
        $result = $this->service->search($query, null);

        $this->assertCount(1, $result->items);
        $this->assertSame('pub-1', $result->items[0]->id);
    }

    // ------------------------------------------------------------------
    // Hybrid scoring
    // ------------------------------------------------------------------

    #[Test]
    public function hybrid_scoring_combines_fts_and_semantic_with_weights(): void
    {
        // Two items appear in both FTS and semantic results.
        $item1 = $this->item('item-1', AccessTier::Public, '2026-01-01T00:00:00+00:00');
        $item2 = $this->item('item-2', AccessTier::Public, '2026-01-02T00:00:00+00:00');

        $this->ftsProvider
            ->method('search')
            ->willReturn($this->ftsResult([
                ['id' => 'knowledge_item:item-1', 'score' => 1.0],
                ['id' => 'knowledge_item:item-2', 'score' => 0.5],
            ]));

        $this->embeddingProvider
            ->method('search')
            ->willReturn([
                ['id' => 'item-1', 'score' => 0.8],
                ['id' => 'item-2', 'score' => 0.4],
            ]);

        $this->repository
            ->method('find')
            ->willReturnCallback(fn (string $id) => match ($id) {
                'item-1' => $item1,
                'item-2' => $item2,
                default  => null,
            });

        $query  = new SearchQuery(query: 'salmon', communityId: self::COMMUNITY);
        $result = $this->service->search($query, $this->memberAccount());

        $this->assertCount(2, $result->items);

        // item-1: semantic normalized = 1.0 (max), fts normalized = 1.0 (max)
        // combined = 0.6*1.0 + 0.4*1.0 = 1.0
        $this->assertSame('item-1', $result->items[0]->id);
        $this->assertEqualsWithDelta(1.0, $result->items[0]->score, 0.001);

        // item-2: semantic normalized = 0.0 (min of [0.8,0.4] => 0.4 => (0.4-0.4)/(0.8-0.4)=0)
        //         fts normalized = 0.0 (min of [1.0,0.5] => (0.5-0.5)/(1.0-0.5)=0)
        // combined = 0.6*0.0 + 0.4*0.0 = 0.0
        $this->assertSame('item-2', $result->items[1]->id);
        $this->assertEqualsWithDelta(0.0, $result->items[1]->score, 0.001);
    }

    #[Test]
    public function hybrid_scoring_item_only_in_fts_uses_fts_weight(): void
    {
        $item1 = $this->item('item-1', AccessTier::Public, '2026-01-01T00:00:00+00:00');

        $this->ftsProvider
            ->method('search')
            ->willReturn($this->ftsResult([
                ['id' => 'knowledge_item:item-1', 'score' => 0.9],
            ]));

        // Semantic returns empty
        $this->embeddingProvider
            ->method('search')
            ->willReturn([]);

        $this->repository
            ->method('find')
            ->with('item-1')
            ->willReturn($item1);

        $query  = new SearchQuery(query: 'cedar', communityId: self::COMMUNITY);
        $result = $this->service->search($query, $this->memberAccount());

        $this->assertCount(1, $result->items);
        // Single FTS item: normalized to 1.0, then weighted: 0.4*1.0
        $this->assertEqualsWithDelta(0.4, $result->items[0]->score, 0.001);
    }

    #[Test]
    public function hybrid_scoring_item_only_in_semantic_uses_semantic_weight(): void
    {
        $item1 = $this->item('item-1', AccessTier::Public, '2026-01-01T00:00:00+00:00');

        // FTS returns empty
        $this->ftsProvider
            ->method('search')
            ->willReturn(SearchResult::empty());

        $this->embeddingProvider
            ->method('search')
            ->willReturn([
                ['id' => 'item-1', 'score' => 0.75],
            ]);

        $this->repository
            ->method('find')
            ->with('item-1')
            ->willReturn($item1);

        $query  = new SearchQuery(query: 'birch', communityId: self::COMMUNITY);
        $result = $this->service->search($query, $this->memberAccount());

        $this->assertCount(1, $result->items);
        // Single semantic item: normalized to 1.0, then weighted: 0.6*1.0
        $this->assertEqualsWithDelta(0.6, $result->items[0]->score, 0.001);
    }

    // ------------------------------------------------------------------
    // Access control
    // ------------------------------------------------------------------

    #[Test]
    public function access_control_filters_staff_tier_from_member_account(): void
    {
        $pubItem   = $this->item('pub-1', AccessTier::Public, '2026-01-01T00:00:00+00:00');
        $staffItem = $this->item('staff-1', AccessTier::Staff, '2026-01-02T00:00:00+00:00');

        $this->ftsProvider
            ->method('search')
            ->willReturn($this->ftsResult([
                ['id' => 'knowledge_item:pub-1',   'score' => 1.0],
                ['id' => 'knowledge_item:staff-1', 'score' => 0.8],
            ]));

        $this->embeddingProvider
            ->method('search')
            ->willReturn([]);

        $this->repository
            ->method('find')
            ->willReturnCallback(fn (string $id) => match ($id) {
                'pub-1'   => $pubItem,
                'staff-1' => $staffItem,
                default   => null,
            });

        $query  = new SearchQuery(query: 'land', communityId: self::COMMUNITY);
        $result = $this->service->search($query, $this->memberAccount());

        $this->assertCount(1, $result->items);
        $this->assertSame('pub-1', $result->items[0]->id);
    }

    #[Test]
    public function null_account_sees_only_public_tier_items_in_search(): void
    {
        $pubItem = $this->item('pub-1', AccessTier::Public, '2026-01-01T00:00:00+00:00');
        $memItem = $this->item('mem-1', AccessTier::Members, '2026-01-02T00:00:00+00:00');

        $this->ftsProvider
            ->method('search')
            ->willReturn($this->ftsResult([
                ['id' => 'knowledge_item:pub-1', 'score' => 1.0],
                ['id' => 'knowledge_item:mem-1', 'score' => 0.9],
            ]));

        $this->embeddingProvider
            ->method('search')
            ->willReturn([]);

        $this->repository
            ->method('find')
            ->willReturnCallback(fn (string $id) => match ($id) {
                'pub-1' => $pubItem,
                'mem-1' => $memItem,
                default => null,
            });

        $query  = new SearchQuery(query: 'water', communityId: self::COMMUNITY);
        $result = $this->service->search($query, null);

        $this->assertCount(1, $result->items);
        $this->assertSame('pub-1', $result->items[0]->id);
    }

    // ------------------------------------------------------------------
    // Multi-word query rewriting (waaseyaa/giiken#61)
    // ------------------------------------------------------------------

    #[Test]
    public function multi_word_query_issues_one_fts_search_per_content_term(): void
    {
        // Content-bearing terms after stopword strip from
        // "what is governance about in this community" are "governance" and "community".
        $seen = [];
        $this->ftsProvider
            ->expects(self::exactly(2))
            ->method('search')
            ->willReturnCallback(function (SearchRequest $r) use (&$seen): SearchResult {
                $seen[] = $r->query;

                return match ($r->query) {
                    'governance' => $this->ftsResult([['id' => 'knowledge_item:gov-1', 'score' => 0.9]]),
                    'community'  => $this->ftsResult([['id' => 'knowledge_item:com-1', 'score' => 0.7]]),
                    default      => SearchResult::empty(),
                };
            });

        $this->embeddingProvider->method('search')->willReturn([]);

        $gov = $this->item('gov-1', AccessTier::Public);
        $com = $this->item('com-1', AccessTier::Public);
        $this->repository
            ->method('find')
            ->willReturnCallback(static fn (string $id) => match ($id) {
                'gov-1' => $gov,
                'com-1' => $com,
                default => null,
            });

        $query = new SearchQuery(
            query: 'what is governance about in this community',
            communityId: self::COMMUNITY,
        );
        $result = $this->service->search($query, $this->memberAccount());

        self::assertSame(['governance', 'community'], $seen);
        self::assertCount(2, $result->items);
    }

    #[Test]
    public function multi_word_query_merges_per_term_hits_by_best_score(): void
    {
        // Same doc hit by two terms — we keep the higher score.
        $this->ftsProvider
            ->method('search')
            ->willReturnCallback(function (SearchRequest $r): SearchResult {
                return match ($r->query) {
                    'governance' => $this->ftsResult([['id' => 'knowledge_item:shared', 'score' => 0.4]]),
                    'overview'   => $this->ftsResult([['id' => 'knowledge_item:shared', 'score' => 0.95]]),
                    default      => SearchResult::empty(),
                };
            });

        $this->embeddingProvider->method('search')->willReturn([]);

        $shared = $this->item('shared', AccessTier::Public);
        $this->repository->method('find')->with('shared')->willReturn($shared);

        $query = new SearchQuery(query: 'governance overview', communityId: self::COMMUNITY);
        $result = $this->service->search($query, $this->memberAccount());

        self::assertCount(1, $result->items);
        self::assertSame('shared', $result->items[0]->id);
        // A single FTS hit normalizes to 1.0, so weighted score is 0.4 regardless
        // of which term supplied it. The point of the test is the doc survives
        // the merge even though each individual FTS call only saw it once.
        self::assertEqualsWithDelta(0.4, $result->items[0]->score, 0.001);
    }

    #[Test]
    public function non_english_locale_preserves_tokens_that_look_like_english_stopwords(): void
    {
        // Anishinaabemowin token "an" (fragment of ani-/an-prefixed verbs) would
        // be dropped by the English stopword list. With locale != 'en' the
        // tokenizer must keep it. We assert the FTS provider is called with
        // both "an" and "governance" (two separate calls), which would not
        // happen under the English path.
        $calls = [];
        $this->ftsProvider
            ->method('search')
            ->willReturnCallback(function (SearchRequest $r) use (&$calls): SearchResult {
                $calls[] = $r->query;

                return SearchResult::empty();
            });

        $this->embeddingProvider->method('search')->willReturn([]);

        $query = new SearchQuery(
            query: 'an governance',
            communityId: self::COMMUNITY,
            locale: 'oj',
        );
        $this->service->search($query, $this->memberAccount());

        self::assertContains('an', $calls);
        self::assertContains('governance', $calls);
    }

    #[Test]
    public function english_locale_still_drops_stopwords(): void
    {
        $calls = [];
        $this->ftsProvider
            ->method('search')
            ->willReturnCallback(function (SearchRequest $r) use (&$calls): SearchResult {
                $calls[] = $r->query;

                return SearchResult::empty();
            });

        $this->embeddingProvider->method('search')->willReturn([]);

        $query = new SearchQuery(
            query: 'an governance',
            communityId: self::COMMUNITY,
            locale: 'en',
        );
        $this->service->search($query, $this->memberAccount());

        self::assertNotContains('an', $calls);
        self::assertContains('governance', $calls);
    }

    #[Test]
    public function null_locale_defaults_to_english_stopword_filtering(): void
    {
        // Backward compatibility: existing callers that don't pass a locale
        // should get the pre-#67 behaviour (English stopwords applied).
        $calls = [];
        $this->ftsProvider
            ->method('search')
            ->willReturnCallback(function (SearchRequest $r) use (&$calls): SearchResult {
                $calls[] = $r->query;

                return SearchResult::empty();
            });

        $this->embeddingProvider->method('search')->willReturn([]);

        $query = new SearchQuery(query: 'an governance', communityId: self::COMMUNITY);
        $this->service->search($query, $this->memberAccount());

        self::assertNotContains('an', $calls);
        self::assertContains('governance', $calls);
    }

    #[Test]
    public function single_character_tokens_are_retained_under_non_english_locale(): void
    {
        // FTS5 accepts single-character tokens. For non-English locales we
        // lower the length floor from 2 to 1 so short stem words survive.
        $calls = [];
        $this->ftsProvider
            ->method('search')
            ->willReturnCallback(function (SearchRequest $r) use (&$calls): SearchResult {
                $calls[] = $r->query;

                return SearchResult::empty();
            });

        $this->embeddingProvider->method('search')->willReturn([]);

        $query = new SearchQuery(
            query: 'n governance',
            communityId: self::COMMUNITY,
            locale: 'oj',
        );
        $this->service->search($query, $this->memberAccount());

        self::assertContains('n', $calls);
        self::assertContains('governance', $calls);
    }

    #[Test]
    public function stopwords_only_query_falls_back_to_single_search(): void
    {
        // "what is the" — everything filters out. We should make exactly one
        // FTS call and hand the raw query through so the vendor escaper gets
        // the same input the caller would have given it.
        $seen = null;
        $this->ftsProvider
            ->expects(self::once())
            ->method('search')
            ->willReturnCallback(function (SearchRequest $r) use (&$seen): SearchResult {
                $seen = $r->query;

                return SearchResult::empty();
            });

        $this->embeddingProvider->method('search')->willReturn([]);

        $query = new SearchQuery(query: 'what is the', communityId: self::COMMUNITY);
        $this->service->search($query, $this->memberAccount());

        self::assertSame('what is the', $seen);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function item(string $id, AccessTier $tier, string $createdAt = '2026-01-01T00:00:00+00:00'): KnowledgeItem
    {
        return KnowledgeItem::make([
            'id'           => $id,
            'community_id' => self::COMMUNITY,
            'title'        => "Item {$id}",
            'content'      => 'Body',
            'access_tier'  => $tier->value,
            'created_at'   => $createdAt,
        ]);
    }

    private function memberAccount(): AccountInterface
    {
        return new class implements AccountInterface {
            public function id(): int|string { return 'user-1'; }
            public function getRoles(): array { return ['giiken.community.comm-1.member']; }
            public function isAuthenticated(): bool { return true; }
            public function hasPermission(string $permission): bool { return false; }
        };
    }

    /**
     * @param array<array{id: string, score: float}> $hits
     */
    private function ftsResult(array $hits): SearchResult
    {
        $searchHits = array_map(
            static fn (array $h) => new SearchHit(
                id: $h['id'],
                title: '',
                url: '',
                sourceName: '',
                crawledAt: '',
                qualityScore: 0,
                contentType: '',
                topics: [],
                score: $h['score'],
            ),
            $hits,
        );

        return new SearchResult(
            totalHits: count($searchHits),
            totalPages: 1,
            currentPage: 1,
            pageSize: 20,
            tookMs: 0,
            hits: $searchHits,
        );
    }
}
