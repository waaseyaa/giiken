<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Query\Report;

use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Query\Report\DateRange;
use Giiken\Query\Report\LandBriefReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LandBriefReport::class)]
final class LandBriefReportTest extends TestCase
{
    private LandBriefReport $renderer;
    private Community $community;
    private DateRange $dateRange;

    protected function setUp(): void
    {
        $this->renderer  = new LandBriefReport();
        $this->community = Community::make(['name' => 'Massey', 'slug' => 'massey']);
        $this->dateRange = new DateRange(
            from: new \DateTimeImmutable('2025-01-01'),
            to: new \DateTimeImmutable('2025-03-31'),
        );
    }

    #[Test]
    public function type_is_land_brief(): void
    {
        $this->assertSame('land_brief', $this->renderer->getType());
    }

    #[Test]
    public function renders_markdown_with_items(): void
    {
        $items = [
            KnowledgeItem::make([
                'title'          => 'Treaty 9 Territory',
                'content'        => 'Historical land use description.',
                'knowledge_type' => KnowledgeType::Land->value,
                'access_tier'    => AccessTier::Public->value,
                'community_id'   => 'comm-1',
            ]),
        ];

        $output = $this->renderer->render($this->community, $items, $this->dateRange);

        $this->assertStringContainsString('# Land Brief: Massey', $output);
        $this->assertStringContainsString('2025-01-01 to 2025-03-31', $output);
        $this->assertStringContainsString('1 land item(s)', $output);
        $this->assertStringContainsString('## Treaty 9 Territory', $output);
        $this->assertStringContainsString('Historical land use description.', $output);
    }

    #[Test]
    public function renders_empty_state(): void
    {
        $output = $this->renderer->render($this->community, [], $this->dateRange);

        $this->assertStringContainsString('No land items found for this period.', $output);
        $this->assertStringNotContainsString('land item(s)', $output);
    }
}
