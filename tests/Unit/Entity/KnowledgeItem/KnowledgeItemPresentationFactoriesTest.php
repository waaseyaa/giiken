<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\KnowledgeItem;

use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\KnowledgeItemMarkdownPresenter;
use App\Entity\KnowledgeItem\KnowledgeItemSearchDocumentFactory;
use App\Entity\KnowledgeItem\KnowledgeItemSearchMetadataFactory;
use App\Entity\KnowledgeItem\KnowledgeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(KnowledgeItemMarkdownPresenter::class)]
#[CoversClass(KnowledgeItemSearchDocumentFactory::class)]
#[CoversClass(KnowledgeItemSearchMetadataFactory::class)]
final class KnowledgeItemPresentationFactoriesTest extends TestCase
{
    #[Test]
    public function markdown_presenter_renders_expected_sections(): void
    {
        $item = $this->makeItem();

        $markdown = (new KnowledgeItemMarkdownPresenter())->render($item);

        self::assertStringContainsString('# Treaty primer', $markdown);
        self::assertStringContainsString('**Type:** Governance', $markdown);
        self::assertStringContainsString('Sources: media-1, media-2', $markdown);
    }

    #[Test]
    public function search_document_factory_maps_title_and_body(): void
    {
        $document = (new KnowledgeItemSearchDocumentFactory())->make($this->makeItem());

        self::assertSame([
            'title' => 'Treaty primer',
            'body' => 'Body text.',
        ], $document);
    }

    #[Test]
    public function search_metadata_factory_maps_search_fields(): void
    {
        $metadata = (new KnowledgeItemSearchMetadataFactory())->make($this->makeItem());

        self::assertSame('knowledge_item', $metadata['entity_type']);
        self::assertSame('comm-1', $metadata['community_id']);
        self::assertSame(KnowledgeType::Governance->value, $metadata['knowledge_type']);
        self::assertSame(AccessTier::Staff->value, $metadata['access_tier']);
    }

    private function makeItem(): KnowledgeItem
    {
        return KnowledgeItem::make([
            'title' => 'Treaty primer',
            'content' => 'Body text.',
            'community_id' => 'comm-1',
            'knowledge_type' => KnowledgeType::Governance->value,
            'access_tier' => AccessTier::Staff->value,
            'compiled_at' => '2026-04-04T12:00:00+00:00',
            'source_media_ids' => json_encode(['media-1', 'media-2'], JSON_THROW_ON_ERROR),
        ]);
    }
}
