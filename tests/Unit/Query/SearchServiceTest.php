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
    // Helpers
    // ------------------------------------------------------------------

    private function item(string $id, AccessTier $tier, string $createdAt = '2026-01-01T00:00:00+00:00'): KnowledgeItem
    {
        return new KnowledgeItem([
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
