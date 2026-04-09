<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Query\Report;

use Giiken\Entity\Community\Community;
use Giiken\Access\KnowledgeItemAccessPolicy;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Query\Report\DateRange;
use Giiken\Query\Report\GovernanceSummaryReport;
use Giiken\Query\Report\LandBriefReport;
use Giiken\Query\Report\LanguageReport;
use Giiken\Query\Report\ReportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(ReportService::class)]
final class ReportServiceTest extends TestCase
{
    private const COMMUNITY_ID = 'comm-1';

    private Community $community;
    private DateRange $dateRange;

    /** @var KnowledgeItemRepositoryInterface&MockObject */
    private KnowledgeItemRepositoryInterface $repository;

    private ReportService $service;

    protected function setUp(): void
    {
        $this->community = new Community(['id' => self::COMMUNITY_ID, 'name' => 'Test Nation', 'slug' => 'test-nation']);
        $this->dateRange = new DateRange(
            from: new \DateTimeImmutable('2025-01-01'),
            to: new \DateTimeImmutable('2025-12-31'),
        );
        $this->repository = $this->createMock(KnowledgeItemRepositoryInterface::class);

        $this->service = new ReportService(
            [
                new GovernanceSummaryReport(),
                new LanguageReport(),
                new LandBriefReport(),
            ],
            $this->repository,
            new KnowledgeItemAccessPolicy(),
        );
    }

    #[Test]
    public function generates_governance_summary_for_staff(): void
    {
        $governanceItem = $this->item(KnowledgeType::Governance, '2025-06-01');
        $culturalItem   = $this->item(KnowledgeType::Cultural, '2025-06-01');

        $this->repository
            ->method('findByCommunity')
            ->with(self::COMMUNITY_ID)
            ->willReturn([$governanceItem, $culturalItem]);

        $account = $this->account(['giiken.community.' . self::COMMUNITY_ID . '.staff']);

        $output = $this->service->generate('governance_summary', $this->community, $this->dateRange, $account);

        $this->assertStringContainsString('# Governance Summary', $output);
        $this->assertStringContainsString('1 governance item(s)', $output);
    }

    #[Test]
    public function governance_summary_denied_for_member(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied: governance_summary requires staff or above');

        $account = $this->account(['giiken.community.' . self::COMMUNITY_ID . '.member']);

        $this->service->generate('governance_summary', $this->community, $this->dateRange, $account);
    }

    #[Test]
    public function land_brief_denied_for_staff(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Access denied: land_brief requires knowledge_keeper or above');

        $account = $this->account(['giiken.community.' . self::COMMUNITY_ID . '.staff']);

        $this->service->generate('land_brief', $this->community, $this->dateRange, $account);
    }

    #[Test]
    public function land_brief_allowed_for_knowledge_keeper(): void
    {
        $landItem = $this->item(KnowledgeType::Land, '2025-03-15');

        $this->repository
            ->method('findByCommunity')
            ->with(self::COMMUNITY_ID)
            ->willReturn([$landItem]);

        $account = $this->account(['giiken.community.' . self::COMMUNITY_ID . '.knowledge_keeper']);

        $output = $this->service->generate('land_brief', $this->community, $this->dateRange, $account);

        $this->assertStringContainsString('# Land Brief', $output);
    }

    #[Test]
    public function filters_by_date_range_using_created_at_when_compiled_at_empty(): void
    {
        $inRange  = $this->item(KnowledgeType::Governance, '2025-06-15');
        $outRange = $this->item(KnowledgeType::Governance, '2024-12-31');

        $this->repository
            ->method('findByCommunity')
            ->willReturn([$inRange, $outRange]);

        $account = $this->account(['giiken.community.' . self::COMMUNITY_ID . '.staff']);

        $output = $this->service->generate('governance_summary', $this->community, $this->dateRange, $account);

        $this->assertStringContainsString('1 governance item(s)', $output);
    }

    #[Test]
    public function filters_by_compiled_at_when_set(): void
    {
        $inRange = $this->item(KnowledgeType::Governance, '2020-01-01', '2025-06-15');
        $ignoredCreated = $this->item(KnowledgeType::Governance, '2025-08-01', '2024-06-01');

        $this->repository
            ->method('findByCommunity')
            ->willReturn([$inRange, $ignoredCreated]);

        $account = $this->account(['giiken.community.' . self::COMMUNITY_ID . '.staff']);

        $output = $this->service->generate('governance_summary', $this->community, $this->dateRange, $account);

        $this->assertStringContainsString('1 governance item(s)', $output);
    }

    #[Test]
    public function unknown_report_type_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $account = $this->account(['giiken.community.' . self::COMMUNITY_ID . '.admin']);

        $this->service->generate('nonexistent_report', $this->community, $this->dateRange, $account);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function item(KnowledgeType $type, string $createdAt, string $compiledAt = ''): KnowledgeItem
    {
        $values = [
            'title'          => 'Item ' . $type->value,
            'content'        => 'Body for ' . $type->value,
            'knowledge_type' => $type->value,
            'community_id'   => self::COMMUNITY_ID,
            'created_at'     => $createdAt,
        ];
        if ($compiledAt !== '') {
            $values['compiled_at'] = $compiledAt;
        }

        return new KnowledgeItem($values);
    }

    /**
     * @param string[] $roles
     */
    private function account(array $roles): AccountInterface
    {
        return new class($roles) implements AccountInterface {
            /** @param string[] $roles */
            public function __construct(private readonly array $roles) {}

            public function id(): int|string { return 'user-1'; }
            public function getRoles(): array { return $this->roles; }
            public function isAuthenticated(): bool { return true; }
            public function hasPermission(string $permission): bool { return false; }
        };
    }
}
