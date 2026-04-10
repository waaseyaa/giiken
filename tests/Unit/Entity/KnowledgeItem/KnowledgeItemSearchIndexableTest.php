<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Entity\KnowledgeItem;

use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Search\SearchIndexableInterface;

#[CoversClass(KnowledgeItem::class)]
final class KnowledgeItemSearchIndexableTest extends TestCase
{
    #[Test]
    public function implements_search_indexable(): void
    {
        $item = $this->item();
        $this->assertInstanceOf(SearchIndexableInterface::class, $item);
    }

    #[Test]
    public function search_document_id_format(): void
    {
        $item = $this->item();
        $item->set('id', '42');
        $this->assertSame('knowledge_item:42', $item->getSearchDocumentId());
    }

    #[Test]
    public function to_search_document_returns_title_and_body_for_fts(): void
    {
        $item = $this->item();
        $doc = $item->toSearchDocument();
        $this->assertSame('Solar Panel Debate', $doc['title']);
        $this->assertSame('Discussion about solar panels in Massey.', $doc['body']);
    }

    #[Test]
    public function to_search_metadata_includes_all_fields(): void
    {
        $item = $this->item();
        $meta = $item->toSearchMetadata();
        $this->assertSame('knowledge_item', $meta['entity_type']);
        $this->assertSame('comm-1', $meta['community_id']);
        $this->assertSame('governance', $meta['knowledge_type']);
        $this->assertSame('public', $meta['access_tier']);
    }

    private function item(): KnowledgeItem
    {
        return KnowledgeItem::make([
            'id'             => '1',
            'community_id'   => 'comm-1',
            'title'          => 'Solar Panel Debate',
            'content'        => 'Discussion about solar panels in Massey.',
            'knowledge_type' => KnowledgeType::Governance->value,
            'access_tier'    => AccessTier::Public->value,
        ]);
    }
}
