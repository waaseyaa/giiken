<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Entity\KnowledgeItem;

use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(KnowledgeItem::class)]
final class KnowledgeItemToMarkdownTest extends TestCase
{
    #[Test]
    public function it_renders_full_item_with_all_fields(): void
    {
        $item = KnowledgeItem::make([
            'community_id'     => 'comm-1',
            'title'            => 'Council Meeting Minutes',
            'content'          => "## Agenda\n\nDiscussion of solar project.",
            'knowledge_type'   => 'governance',
            'access_tier'      => 'public',
            'compiled_at'      => '2026-04-04T12:00:00+00:00',
            'source_media_ids' => json_encode(['media-1', 'media-2'], JSON_THROW_ON_ERROR),
        ]);

        $md = $item->toMarkdown();

        $this->assertStringContainsString('# Council Meeting Minutes', $md);
        $this->assertStringContainsString('**Type:** Governance', $md);
        $this->assertStringContainsString('**Access:** Public', $md);
        $this->assertStringContainsString('## Agenda', $md);
        $this->assertStringContainsString('Discussion of solar project.', $md);
        $this->assertStringContainsString('media-1, media-2', $md);
    }

    #[Test]
    public function it_omits_null_knowledge_type(): void
    {
        $item = KnowledgeItem::make([
            'community_id' => 'comm-1',
            'title'        => 'Untitled',
            'content'      => 'Some content.',
        ]);

        $md = $item->toMarkdown();

        $this->assertStringContainsString('# Untitled', $md);
        $this->assertStringNotContainsString('**Type:**', $md);
        $this->assertStringContainsString('Some content.', $md);
    }

    #[Test]
    public function it_omits_empty_source_media_ids(): void
    {
        $item = KnowledgeItem::make([
            'community_id' => 'comm-1',
            'title'        => 'No Sources',
            'content'      => 'Content.',
        ]);

        $md = $item->toMarkdown();

        $this->assertStringNotContainsString('Sources:', $md);
    }

    #[Test]
    public function it_omits_empty_compiled_at(): void
    {
        $item = KnowledgeItem::make([
            'community_id' => 'comm-1',
            'title'        => 'Uncompiled',
            'content'      => 'Draft.',
        ]);

        $md = $item->toMarkdown();

        $this->assertStringNotContainsString('**Compiled:**', $md);
    }
}
