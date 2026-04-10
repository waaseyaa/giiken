<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Wiki\Check;

use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Wiki\Check\LintCheckInterface;
use Giiken\Wiki\Check\OrphanPageCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrphanPageCheck::class)]
final class OrphanPageCheckTest extends TestCase
{
    #[Test]
    public function it_implements_lint_check_interface(): void
    {
        $check = new OrphanPageCheck();
        self::assertInstanceOf(LintCheckInterface::class, $check);
    }

    #[Test]
    public function it_detects_orphan_pages(): void
    {
        $check = new OrphanPageCheck();

        // B links to A, so A has inbound. C and D have no inbound links.
        $items = [
            $this->makeItem('page-a', 'Page A', 'See [[page-b]] for details.'),
            $this->makeItem('page-b', 'Page B', 'Back to [[page-a]].'),
            $this->makeItem('page-c', 'Page C', 'Nobody links here.'),
            $this->makeItem('page-d', 'Page D', 'Links out to [[page-a]] but nobody points here.'),
        ];

        $findings = $check->run($items);

        $orphanIds = array_column($findings, 'item_id');
        sort($orphanIds);

        self::assertSame(['page-c', 'page-d'], $orphanIds);
        self::assertSame('orphan_page', $findings[0]['type']);
    }

    #[Test]
    public function it_returns_empty_when_all_pages_linked(): void
    {
        $check = new OrphanPageCheck();

        $items = [
            $this->makeItem('page-a', 'Page A', 'See [[page-b]].'),
            $this->makeItem('page-b', 'Page B', 'See [[page-a]].'),
        ];

        $findings = $check->run($items);

        self::assertCount(0, $findings);
    }

    #[Test]
    public function it_treats_single_page_as_orphan(): void
    {
        $check = new OrphanPageCheck();

        $items = [
            $this->makeItem('lonely', 'Lonely Page', 'No links anywhere.'),
        ];

        $findings = $check->run($items);

        self::assertCount(1, $findings);
    }

    private function makeItem(string $id, string $title, string $body): KnowledgeItem
    {
        return KnowledgeItem::make([
            'id' => $id,
            'title' => $title,
            'body' => $body,
            'community_id' => 'test-community',
            'knowledge_type' => 'general',
            'access_tier' => 'public',
        ]);
    }
}
