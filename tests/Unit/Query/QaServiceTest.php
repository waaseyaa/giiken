<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Query;

use Giiken\Pipeline\Provider\LlmProviderInterface;
use Giiken\Query\QaResponse;
use Giiken\Query\QaService;
use Giiken\Query\SearchQuery;
use Giiken\Query\SearchResultItem;
use Giiken\Query\SearchResultSet;
use Giiken\Query\SearchService;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(QaService::class)]
#[CoversClass(QaResponse::class)]
final class QaServiceTest extends TestCase
{
    private const COMMUNITY = 'comm-1';

    private SearchService&MockObject $searchService;
    private LlmProviderInterface&MockObject $llmProvider;
    private QaService $service;
    private AccountInterface&MockObject $account;

    protected function setUp(): void
    {
        $this->searchService = $this->createMock(SearchService::class);
        $this->llmProvider   = $this->createMock(LlmProviderInterface::class);
        $this->account       = $this->createMock(AccountInterface::class);

        $this->service = new QaService($this->searchService, $this->llmProvider);
    }

    #[Test]
    public function successfulQaReturnsParsedCitations(): void
    {
        $item1 = new SearchResultItem(
            id: 'item-1',
            title: 'Land Rights Overview',
            summary: 'A summary of land rights.',
            knowledgeType: KnowledgeType::Land,
            score: 0.9,
        );
        $item2 = new SearchResultItem(
            id: 'item-2',
            title: 'Governance Principles',
            summary: 'Community governance details.',
            knowledgeType: KnowledgeType::Governance,
            score: 0.8,
        );

        $resultSet = new SearchResultSet(
            items: [$item1, $item2],
            totalHits: 2,
            totalPages: 1,
        );

        $this->searchService
            ->expects($this->once())
            ->method('search')
            ->willReturn($resultSet);

        $llmAnswer = 'Based on the knowledge base, land rights are described in [item-1] and governance principles in [item-2].';

        $this->llmProvider
            ->expects($this->once())
            ->method('complete')
            ->willReturn($llmAnswer);

        $response = $this->service->ask('What are land rights?', self::COMMUNITY, $this->account);

        $this->assertInstanceOf(QaResponse::class, $response);
        $this->assertFalse($response->noRelevantItems);
        $this->assertSame($llmAnswer, $response->answer);
        $this->assertEqualsCanonicalizing(['item-1', 'item-2'], $response->citedItemIds);
    }

    #[Test]
    public function noResultsReturnsNoRelevantItemsWithoutCallingLlm(): void
    {
        $this->searchService
            ->expects($this->once())
            ->method('search')
            ->willReturn(SearchResultSet::empty());

        $this->llmProvider
            ->expects($this->never())
            ->method('complete');

        $response = $this->service->ask('What is the sky?', self::COMMUNITY, $this->account);

        $this->assertTrue($response->noRelevantItems);
        $this->assertSame(
            "I don't have enough information in this community's knowledge base to answer that question.",
            $response->answer,
        );
        $this->assertSame([], $response->citedItemIds);
    }

    #[Test]
    public function citationParsingHandlesMultipleFormatsAndDeduplicates(): void
    {
        $item = new SearchResultItem(
            id: 'item-abc',
            title: 'Cultural Practices',
            summary: 'Cultural summary here.',
            knowledgeType: KnowledgeType::Cultural,
            score: 0.95,
        );

        $resultSet = new SearchResultSet(
            items: [$item],
            totalHits: 1,
            totalPages: 1,
        );

        $this->searchService
            ->expects($this->once())
            ->method('search')
            ->willReturn($resultSet);

        // Answer cites item-abc twice and item-def-456 once
        $llmAnswer = 'See [item-abc] for cultural detail. Also [item-def-456] is relevant. [item-abc] confirms this.';

        $this->llmProvider
            ->expects($this->once())
            ->method('complete')
            ->willReturn($llmAnswer);

        $response = $this->service->ask('Tell me about culture.', self::COMMUNITY, $this->account);

        $this->assertFalse($response->noRelevantItems);
        // item-abc should appear once (deduplicated), item-def-456 once
        $this->assertCount(2, $response->citedItemIds);
        $this->assertContains('item-abc', $response->citedItemIds);
        $this->assertContains('item-def-456', $response->citedItemIds);
    }

    #[Test]
    public function searchQueryIsBuiltCorrectly(): void
    {
        $this->searchService
            ->expects($this->once())
            ->method('search')
            ->with(
                $this->callback(function (SearchQuery $query): bool {
                    return $query->query === 'What is governance?'
                        && $query->communityId === self::COMMUNITY
                        && $query->page === 1
                        && $query->pageSize === 5;
                }),
                $this->account,
            )
            ->willReturn(SearchResultSet::empty());

        $this->service->ask('What is governance?', self::COMMUNITY, $this->account);
    }
}
