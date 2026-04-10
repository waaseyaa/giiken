<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Query\Report;

use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Query\Report\DateRange;
use Giiken\Query\Report\GovernanceSummaryReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(GovernanceSummaryReport::class)]
final class GovernanceSummaryReportTest extends TestCase
{
    private GovernanceSummaryReport $renderer;
    private Community $community;
    private DateRange $dateRange;

    protected function setUp(): void
    {
        $this->renderer  = new GovernanceSummaryReport();
        $this->community = Community::make(['name' => 'Massey', 'slug' => 'massey']);
        $this->dateRange = new DateRange(
            from: new \DateTimeImmutable('2025-01-01'),
            to: new \DateTimeImmutable('2025-03-31'),
        );
    }

    #[Test]
    public function type_is_governance_summary(): void
    {
        $this->assertSame('governance_summary', $this->renderer->getType());
    }

    #[Test]
    public function renders_markdown_with_items(): void
    {
        $items = [
            KnowledgeItem::make([
                'title'          => 'Council Minutes',
                'content'        => 'Quorum was reached.',
                'knowledge_type' => KnowledgeType::Governance->value,
                'access_tier'    => AccessTier::Public->value,
                'community_id'   => 'comm-1',
            ]),
        ];

        $output = $this->renderer->render($this->community, $items, $this->dateRange);

        $this->assertStringContainsString('# Governance Summary: Massey', $output);
        $this->assertStringContainsString('2025-01-01 to 2025-03-31', $output);
        $this->assertStringContainsString('1 governance item(s)', $output);
        $this->assertStringContainsString('## Council Minutes', $output);
        $this->assertStringContainsString('Quorum was reached.', $output);
    }

    #[Test]
    public function renders_empty_state(): void
    {
        $output = $this->renderer->render($this->community, [], $this->dateRange);

        $this->assertStringContainsString('No governance items found for this period.', $output);
        $this->assertStringNotContainsString('governance item(s)', $output);
    }
}
