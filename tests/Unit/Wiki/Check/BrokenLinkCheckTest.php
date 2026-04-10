<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Wiki\Check;

use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Wiki\Check\BrokenLinkCheck;
use Giiken\Wiki\Check\LintCheckInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BrokenLinkCheck::class)]
final class BrokenLinkCheckTest extends TestCase
{
    #[Test]
    public function it_implements_lint_check_interface(): void
    {
        $check = new BrokenLinkCheck();
        self::assertInstanceOf(LintCheckInterface::class, $check);
    }

    #[Test]
    public function it_detects_broken_links(): void
    {
        $check = new BrokenLinkCheck();

        $items = [
            $this->makeItem('page-a', 'Page A', 'See [[page-b]] and [[page-missing]].'),
            $this->makeItem('page-b', 'Page B', 'Links to [[also-missing]].'),
        ];

        $findings = $check->run($items);

        self::assertCount(2, $findings);

        $itemIds = array_column($findings, 'item_id');
        self::assertContains('page-a', $itemIds);
        self::assertContains('page-b', $itemIds);

        $types = array_unique(array_column($findings, 'type'));
        self::assertSame(['broken_link'], $types);
    }

    #[Test]
    public function it_returns_empty_when_all_links_valid(): void
    {
        $check = new BrokenLinkCheck();

        $items = [
            $this->makeItem('page-a', 'Page A', 'See [[page-b]].'),
            $this->makeItem('page-b', 'Page B', 'See [[page-a]].'),
        ];

        $findings = $check->run($items);

        self::assertCount(0, $findings);
    }

    #[Test]
    public function it_handles_pages_with_no_links(): void
    {
        $check = new BrokenLinkCheck();

        $items = [
            $this->makeItem('page-a', 'Page A', 'No links here.'),
        ];

        $findings = $check->run($items);

        self::assertCount(0, $findings);
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
