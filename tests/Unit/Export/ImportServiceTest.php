<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Export;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Export\ExportService;
use Giiken\Export\ImportResult;
use Giiken\Export\ImportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(ImportService::class)]
#[CoversClass(ImportResult::class)]
final class ImportServiceTest extends TestCase
{
    private KnowledgeItemRepositoryInterface&MockObject $itemRepository;

    protected function setUp(): void
    {
        $this->itemRepository = $this->createMock(KnowledgeItemRepositoryInterface::class);
    }

    #[Test]
    public function round_trip_preserves_community_and_items(): void
    {
        $communityId = 'comm-round-trip';
        $community   = Community::make([
            'id'                  => $communityId,
            'uuid'                => $communityId . '-uuid',
            'name'                => 'Round Trip Nation',
            'slug'                => 'round-trip-nation',
            'locale'              => 'en',
            'sovereignty_profile' => 'local',
            'contact_email'       => 'admin@roundtrip.test',
        ]);

        $items = [
            $this->knowledgeItem('uuid-1', $communityId, 'Item One', 'Content of item one.'),
            $this->knowledgeItem('uuid-2', $communityId, 'Item Two', 'Content of item two.'),
        ];

        // Real ExportService to create a ZIP
        $exportItemRepo = $this->createMock(KnowledgeItemRepositoryInterface::class);

        $exportItemRepo
            ->method('findByCommunity')
            ->willReturn($items);

        $exportService = new ExportService(
            itemRepository: $exportItemRepo,
        );

        $zipPath = $exportService->export($community, $this->adminAccount($communityId));

        // Track saved entities
        /** @var Community|null $savedCommunity */
        $savedCommunity = null;

        /** @var KnowledgeItem[] $savedItems */
        $savedItems = [];

        // Anonymous class community repository
        $communityRepository = new class ($communityId) implements CommunityRepositoryInterface {
            public ?Community $captured = null;

            public function __construct(
                private readonly string $communityId,
            ) {}

            public function find(string $id): ?Community
            {
                return null;
            }

            public function findBySlug(string $slug): ?Community
            {
                return null;
            }

            public function save(Community $community): void
            {
                $community->set('id', $this->communityId);
                $this->captured = $community;
            }

            public function delete(Community $community): void {}
        };

        $this->itemRepository
            ->method('save')
            ->willReturnCallback(function (KnowledgeItem $item) use (&$savedItems): void {
                $savedItems[] = $item;
            });

        $importService = new ImportService(
            communityRepository: $communityRepository,
            itemRepository: $this->itemRepository,
        );

        $result = $importService->import($zipPath, $this->adminAccount($communityId));

        unlink($zipPath);

        // Community name survives round-trip
        $this->assertNotNull($communityRepository->captured);
        $this->assertSame('Round Trip Nation', $communityRepository->captured->name());

        // Both items were imported
        $this->assertSame(2, $result->itemsImported);
        $this->assertCount(2, $savedItems);

        $this->assertInstanceOf(ImportResult::class, $result);
    }

    #[Test]
    public function import_denied_for_non_admin(): void
    {
        // Throwaway zip — access check fires before extraction
        $tmpZip = sys_get_temp_dir() . '/giiken-test-' . uniqid('', true) . '.zip';
        $zip    = new \ZipArchive();
        $zip->open($tmpZip, \ZipArchive::CREATE);
        $zip->addFromString('community.yaml', "name: Test\nslug: test\nlocale: en\n");
        $zip->close();

        $communityRepository = new class implements CommunityRepositoryInterface {
            public function find(string $id): ?Community { return null; }
            public function findBySlug(string $slug): ?Community { return null; }
            public function save(Community $community): void {}
            public function delete(Community $community): void {}
        };

        $importService = new ImportService(
            communityRepository: $communityRepository,
            itemRepository: $this->itemRepository,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied: import requires admin role');

        try {
            $importService->import($tmpZip, $this->memberAccount('comm-1'));
        } finally {
            @unlink($tmpZip);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function knowledgeItem(
        string $uuid,
        string $communityId,
        string $title,
        string $content = 'Body.',
    ): KnowledgeItem {
        return new KnowledgeItem([
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
