<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Query;

use Giiken\Query\SearchQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SearchQuery::class)]
final class SearchQueryTest extends TestCase
{
    #[Test]
    public function defaults(): void
    {
        $query = new SearchQuery(query: 'solar panels', communityId: 'comm-1');

        $this->assertSame('solar panels', $query->query);
        $this->assertSame('comm-1', $query->communityId);
        $this->assertSame([], $query->filters);
        $this->assertSame(1, $query->page);
        $this->assertSame(20, $query->pageSize);
    }

    #[Test]
    public function custom_values(): void
    {
        $query = new SearchQuery(
            query: 'governance',
            communityId: 'comm-2',
            filters: ['knowledge_type' => 'governance'],
            page: 3,
            pageSize: 10,
        );

        $this->assertSame(['knowledge_type' => 'governance'], $query->filters);
        $this->assertSame(3, $query->page);
        $this->assertSame(10, $query->pageSize);
    }

    #[Test]
    public function empty_query_is_valid(): void
    {
        $query = new SearchQuery(query: '', communityId: 'comm-1');

        $this->assertSame('', $query->query);
    }
}
