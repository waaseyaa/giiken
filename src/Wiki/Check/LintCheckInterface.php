<?php

declare(strict_types=1);

namespace Giiken\Wiki\Check;

use Giiken\Entity\KnowledgeItem\KnowledgeItem;

interface LintCheckInterface
{
    /**
     * Run this check against a set of knowledge items.
     *
     * @param list<KnowledgeItem> $items
     * @return list<array{item_id: string, type: string, message: string}>
     */
    public function run(array $items): array;
}
