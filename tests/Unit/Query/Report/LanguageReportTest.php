<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Query\Report;

use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Query\Report\DateRange;
use Giiken\Query\Report\LanguageReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LanguageReport::class)]
final class LanguageReportTest extends TestCase
{
    private LanguageReport $renderer;
    private Community $community;
    private DateRange $dateRange;

    protected function setUp(): void
    {
        $this->renderer  = new LanguageReport();
        $this->community = new Community(['name' => 'Massey', 'slug' => 'massey']);
        $this->dateRange = new DateRange(
            from: new \DateTimeImmutable('2025-01-01'),
            to: new \DateTimeImmutable('2025-03-31'),
        );
    }

    #[Test]
    public function type_is_language_report(): void
    {
        $this->assertSame('language_report', $this->renderer->getType());
    }

    #[Test]
    public function renders_markdown_with_items(): void
    {
        $items = [
            new KnowledgeItem([
                'title'          => 'Ojibwe Greetings',
                'content'        => 'Boozhoo means hello.',
                'knowledge_type' => KnowledgeType::Cultural->value,
                'access_tier'    => AccessTier::Public->value,
                'community_id'   => 'comm-1',
            ]),
        ];

        $output = $this->renderer->render($this->community, $items, $this->dateRange);

        $this->assertStringContainsString('# Language & Cultural Report: Massey', $output);
        $this->assertStringContainsString('2025-01-01 to 2025-03-31', $output);
        $this->assertStringContainsString('1 cultural item(s)', $output);
        $this->assertStringContainsString('## Ojibwe Greetings', $output);
        $this->assertStringContainsString('Boozhoo means hello.', $output);
    }

    #[Test]
    public function renders_empty_state(): void
    {
        $output = $this->renderer->render($this->community, [], $this->dateRange);

        $this->assertStringContainsString('No cultural items found for this period.', $output);
        $this->assertStringNotContainsString('cultural item(s)', $output);
    }
}
