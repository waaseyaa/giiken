<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Entity\KnowledgeItem;

use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(KnowledgeItem::class)]
final class KnowledgeItemTest extends TestCase
{
    #[Test]
    public function it_sets_created_at_automatically(): void
    {
        $item = new KnowledgeItem([
            'community_id' => 'abc',
            'title'        => 'Test',
            'content'      => 'Body',
        ]);

        $this->assertNotEmpty($item->getCreatedAt());
    }

    #[Test]
    public function it_returns_all_scalar_fields(): void
    {
        $item = new KnowledgeItem([
            'community_id'   => 'community-uuid',
            'title'          => 'Land History',
            'content'        => '# Land History\n\nContent here.',
            'knowledge_type' => 'land',
            'access_tier'    => 'members',
            'compiled_at'    => '2026-04-04T12:00:00+00:00',
        ]);

        $this->assertSame('community-uuid', $item->getCommunityId());
        $this->assertSame('Land History', $item->getTitle());
        $this->assertSame('# Land History\n\nContent here.', $item->getContent());
        $this->assertSame(KnowledgeType::Land, $item->getKnowledgeType());
        $this->assertSame(AccessTier::Members, $item->getAccessTier());
        $this->assertSame('2026-04-04T12:00:00+00:00', $item->getCompiledAt());
    }

    #[Test]
    public function access_tier_defaults_to_members_for_unknown_value(): void
    {
        $item = new KnowledgeItem([
            'community_id' => 'abc',
            'title'        => 'Test',
            'content'      => 'Body',
            'access_tier'  => 'nonexistent',
        ]);

        $this->assertSame(AccessTier::Members, $item->getAccessTier());
    }

    #[Test]
    public function knowledge_type_returns_null_when_not_set(): void
    {
        $item = new KnowledgeItem([
            'community_id' => 'abc',
            'title'        => 'Test',
            'content'      => 'Body',
        ]);

        $this->assertNull($item->getKnowledgeType());
    }

    #[Test]
    public function it_stores_and_retrieves_json_arrays(): void
    {
        $item = new KnowledgeItem([
            'community_id'    => 'abc',
            'title'           => 'Restricted Item',
            'content'         => 'Body',
            'access_tier'     => 'restricted',
            'allowed_roles'   => json_encode(['knowledge_keeper'], JSON_THROW_ON_ERROR),
            'allowed_users'   => json_encode([], JSON_THROW_ON_ERROR),
            'source_media_ids' => json_encode(['media-1', 'media-2'], JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame(['knowledge_keeper'], $item->getAllowedRoles());
        $this->assertSame([], $item->getAllowedUsers());
        $this->assertSame(['media-1', 'media-2'], $item->getSourceMediaIds());
        $this->assertSame(AccessTier::Restricted, $item->getAccessTier());
    }

    #[Test]
    public function json_arrays_return_empty_on_corrupt_data(): void
    {
        $item = new KnowledgeItem([
            'community_id'  => 'abc',
            'title'         => 'Test',
            'content'       => 'Body',
            'allowed_roles' => '{not json',
        ]);

        $this->assertSame([], $item->getAllowedRoles());
    }

    #[Test]
    public function it_implements_has_community(): void
    {
        $this->assertContains(
            \Giiken\Entity\HasCommunity::class,
            class_implements(KnowledgeItem::class) ?: [],
        );
    }
}
