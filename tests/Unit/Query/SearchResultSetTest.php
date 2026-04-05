<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Query;

use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Query\SearchResultItem;
use Giiken\Query\SearchResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchResultSet::class)]
#[CoversClass(SearchResultItem::class)]
final class SearchResultSetTest extends TestCase
{
    #[Test]
    public function empty_result_set(): void
    {
        $result = SearchResultSet::empty();

        $this->assertSame([], $result->items);
        $this->assertSame(0, $result->totalHits);
        $this->assertSame(0, $result->totalPages);
    }

    #[Test]
    public function result_set_with_items(): void
    {
        $item = new SearchResultItem(
            id: 'item-1',
            title: 'Solar Panel Debate',
            summary: 'Community discussion about solar panels.',
            knowledgeType: KnowledgeType::Governance,
            score: 0.85,
        );

        $result = new SearchResultSet(
            items: [$item],
            totalHits: 1,
            totalPages: 1,
        );

        $this->assertCount(1, $result->items);
        $this->assertSame('item-1', $result->items[0]->id);
        $this->assertSame(0.85, $result->items[0]->score);
        $this->assertSame(KnowledgeType::Governance, $result->items[0]->knowledgeType);
    }
}
