<?php

declare(strict_types=1);

namespace App\Tests\Unit\Wiki;

use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Wiki\Check\BrokenLinkCheck;
use App\Wiki\Check\LintCheckInterface;
use App\Wiki\Check\OrphanPageCheck;
use App\Wiki\WikiLintJob;
use App\Wiki\WikiLintReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

#[CoversClass(WikiLintJob::class)]
final class WikiLintJobTest extends TestCase
{
    #[Test]
    public function it_runs_all_checks_and_produces_report(): void
    {
        $items = [
            $this->makeItem('page-a', 'See [[page-b]] and [[missing]].'),
            $this->makeItem('page-b', 'No links.'),
            $this->makeItem('page-c', 'Orphan with [[also-missing]].'),
        ];

        $repository = $this->createMock(EntityRepositoryInterface::class);
        $repository->method('findBy')->willReturn($items);

        $savedReport = null;
        $repository->method('save')->willReturnCallback(function (object $entity) use (&$savedReport): int {
            if ($entity instanceof WikiLintReport) {
                $savedReport = $entity;
            }
            return 1;
        });

        $job = new WikiLintJob(
            communityId: 'test-community',
            repository: $repository,
            checks: [new OrphanPageCheck(), new BrokenLinkCheck()],
        );

        $job->handle();

        self::assertNotNull($savedReport);
        self::assertInstanceOf(WikiLintReport::class, $savedReport);

        $findings = $savedReport->getFindings();
        self::assertNotEmpty($findings);

        $types = array_unique(array_column($findings, 'type'));
        sort($types);
        self::assertSame(['broken_link', 'orphan_page'], $types);
    }

    #[Test]
    public function it_produces_clean_report_when_no_issues(): void
    {
        $items = [
            $this->makeItem('page-a', 'See [[page-b]].'),
            $this->makeItem('page-b', 'See [[page-a]].'),
        ];

        $repository = $this->createMock(EntityRepositoryInterface::class);
        $repository->method('findBy')->willReturn($items);

        $savedReport = null;
        $repository->method('save')->willReturnCallback(function (object $entity) use (&$savedReport): int {
            if ($entity instanceof WikiLintReport) {
                $savedReport = $entity;
            }
            return 1;
        });

        $job = new WikiLintJob(
            communityId: 'test-community',
            repository: $repository,
            checks: [new OrphanPageCheck(), new BrokenLinkCheck()],
        );

        $job->handle();

        self::assertNotNull($savedReport);
        self::assertSame(0, $savedReport->getFindingCount());
    }

    private function makeItem(string $id, string $body): KnowledgeItem
    {
        return KnowledgeItem::make([
            'id' => $id,
            'title' => ucfirst(str_replace('-', ' ', $id)),
            'body' => $body,
            'community_id' => 'test-community',
            'knowledge_type' => 'general',
            'access_tier' => 'public',
        ]);
    }
}
