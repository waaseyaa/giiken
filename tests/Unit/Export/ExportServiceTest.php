<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Export;

use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Export\ExportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use ZipArchive;

#[CoversClass(ExportService::class)]
final class ExportServiceTest extends TestCase
{
    private KnowledgeItemRepositoryInterface&MockObject $itemRepository;
    private ExportService $service;

    protected function setUp(): void
    {
        $this->itemRepository = $this->createMock(KnowledgeItemRepositoryInterface::class);

        $this->service = new ExportService(
            itemRepository: $this->itemRepository,
        );
    }

    #[Test]
    public function export_creates_zip_with_expected_structure(): void
    {
        $community = $this->community('comm-1', 'Test Nation');

        $this->itemRepository
            ->method('findByCommunity')
            ->with('comm-1')
            ->willReturn([
                $this->knowledgeItem('item-1', 'comm-1', 'First Item'),
            ]);

        $zipPath = $this->service->export($community, $this->adminAccount('comm-1'));

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive();
        $this->assertSame(true, $zip->open($zipPath));

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name !== false) {
                $names[] = $name;
            }
        }

        $zip->close();

        $this->assertContains('community.yaml', $names);
        $this->assertContains('embeddings.json', $names);
        $this->assertContains('users.yaml', $names);
        $this->assertContains('README.md', $names);

        // At least one knowledge item file exists
        $hasItemFile = false;
        foreach ($names as $name) {
            if (str_starts_with($name, 'knowledge-items/')) {
                $hasItemFile = true;
                break;
            }
        }
        $this->assertTrue($hasItemFile, 'ZIP should contain at least one knowledge-items/*.md file');

        unlink($zipPath);
    }

    #[Test]
    public function export_knowledge_item_as_markdown_with_frontmatter(): void
    {
        $community = $this->community('comm-1', 'Test Nation');
        $item      = $this->knowledgeItem('item-uuid-42', 'comm-1', 'Sacred Waters', 'This is the content body.');

        $this->itemRepository
            ->method('findByCommunity')
            ->willReturn([$item]);

        $zipPath = $this->service->export($community, $this->adminAccount('comm-1'));

        $zip = new ZipArchive();
        $zip->open($zipPath);

        $mdContent = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (is_string($name) && str_starts_with($name, 'knowledge-items/') && str_ends_with($name, '.md')) {
                $mdContent = $zip->getFromIndex($i);
                break;
            }
        }

        $zip->close();
        unlink($zipPath);

        $this->assertIsString($mdContent, 'No markdown file found in knowledge-items/');
        $this->assertStringContainsString('title: Sacred Waters', $mdContent);
        $this->assertStringContainsString('knowledge_type:', $mdContent);
        $this->assertStringContainsString('---', $mdContent);
        $this->assertStringContainsString('This is the content body.', $mdContent);
    }

    #[Test]
    public function export_denied_for_non_admin(): void
    {
        $community = $this->community('comm-1', 'Test Nation');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied: export requires admin role');

        $this->service->export($community, $this->memberAccount('comm-1'));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function community(string $id, string $name): Community
    {
        return Community::make([
            'id'                  => $id,
            'uuid'                => $id . '-uuid',
            'name'                => $name,
            'slug'                => strtolower(str_replace(' ', '-', $name)),
            'locale'              => 'en',
            'sovereignty_profile' => 'local',
            'contact_email'       => 'admin@example.com',
        ]);
    }

    private function knowledgeItem(
        string $uuid,
        string $communityId,
        string $title,
        string $content = 'Default content.',
    ): KnowledgeItem {
        return KnowledgeItem::make([
            'id'             => $uuid,
            'uuid'           => $uuid,
            'community_id'   => $communityId,
            'title'          => $title,
            'content'        => $content,
            'knowledge_type' => KnowledgeType::Cultural->value,
            'access_tier'    => AccessTier::Members->value,
            'created_at'     => '2026-01-01T00:00:00+00:00',
            'updated_at'     => '2026-01-02T00:00:00+00:00',
        ]);
    }

    private function adminAccount(string $communityId): AccountInterface
    {
        return new class ($communityId) implements AccountInterface {
            public function __construct(private readonly string $communityId) {}
            public function id(): int|string { return 'admin-user'; }
            public function getRoles(): array { return ["giiken.community.{$this->communityId}.admin"]; }
            public function isAuthenticated(): bool { return true; }
            public function hasPermission(string $permission): bool { return true; }
        };
    }

    private function memberAccount(string $communityId): AccountInterface
    {
        return new class ($communityId) implements AccountInterface {
            public function __construct(private readonly string $communityId) {}
            public function id(): int|string { return 'member-user'; }
            public function getRoles(): array { return ["giiken.community.{$this->communityId}.member"]; }
            public function isAuthenticated(): bool { return true; }
            public function hasPermission(string $permission): bool { return false; }
        };
    }
}
