<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Query;

use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Pipeline\Provider\LlmProviderInterface;
use Giiken\Query\QaService;
use Giiken\Query\SearchResultItem;
use Giiken\Query\SearchResultSet;
use Giiken\Query\SearchService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(QaService::class)]
final class QaServiceTest extends TestCase
{
    /** @var SearchService&MockObject */
    private SearchService $search;
    /** @var LlmProviderInterface&MockObject */
    private LlmProviderInterface $llm;
    private QaService $qa;

    protected function setUp(): void
    {
        $this->search = $this->createMock(SearchService::class);
        $this->llm    = $this->createMock(LlmProviderInterface::class);
        $this->qa     = new QaService($this->search, $this->llm);
    }

    #[Test]
    public function it_returns_structured_citations_for_ids_in_answer(): void
    {
        $items = [
            new SearchResultItem('42', 'Treaty primer', 'Summary about treaties.', KnowledgeType::Governance, 0.9),
            new SearchResultItem('7', 'Other', 'Unrelated.', KnowledgeType::Cultural, 0.5),
        ];
        $this->search->method('search')->willReturn(new SearchResultSet(
            items: $items,
            totalHits: 2,
            totalPages: 1,
        ));

        $this->llm->method('complete')->willReturn(
            'The treaty process is documented in source [42].',
        );

        $account = $this->createStub(AccountInterface::class);
        $r       = $this->qa->ask('What about treaties?', 'comm-1', $account);

        self::assertSame(['42'], $r->citedItemIds);
        self::assertCount(1, $r->citations);
        self::assertSame('42', $r->citations[0]->itemId);
        self::assertSame('Treaty primer', $r->citations[0]->title);
        self::assertStringContainsString('Summary about', $r->citations[0]->excerpt);
    }

    #[Test]
    public function it_strips_citations_not_in_search_results(): void
    {
        $items = [new SearchResultItem('1', 'Only', 'Body', KnowledgeType::Land, 1.0)];
        $this->search->method('search')->willReturn(new SearchResultSet($items, 1, 1));
        $this->llm->method('complete')->willReturn('See [999] and [1].');

        $account = $this->createStub(AccountInterface::class);
        $r       = $this->qa->ask('Q', 'c', $account);

        self::assertSame(['1'], $r->citedItemIds);
    }

    #[Test]
    public function empty_search_yields_no_citations(): void
    {
        $this->search->method('search')->willReturn(SearchResultSet::empty());

        $this->llm->expects(self::never())->method('complete');

        $r = $this->qa->ask('Q', 'c', null);
        self::assertTrue($r->noRelevantItems);
        self::assertSame([], $r->citations);
    }

    #[Test]
    public function citation_excerpt_truncates_on_utf8_codepoints_not_bytes(): void
    {
        $longSummary = str_repeat('é', 400);
        $items       = [
            new SearchResultItem('1', 'T', $longSummary, KnowledgeType::Cultural, 1.0),
        ];
        $this->search->method('search')->willReturn(new SearchResultSet($items, 1, 1));
        $this->llm->method('complete')->willReturn('Answer [1].');

        $r = $this->qa->ask('Q', 'c', $this->createStub(AccountInterface::class));

        self::assertCount(1, $r->citations);
        $excerpt = $r->citations[0]->excerpt;
        self::assertTrue(mb_check_encoding($excerpt, 'UTF-8'));
        self::assertLessThanOrEqual(280, mb_strlen($excerpt, 'UTF-8'));
        self::assertStringEndsWith('…', $excerpt);
    }
}
