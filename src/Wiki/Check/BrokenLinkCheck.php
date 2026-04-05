<?php

declare(strict_types=1);

namespace Giiken\Wiki\Check;

use Giiken\Entity\KnowledgeItem\KnowledgeItem;

final class BrokenLinkCheck implements LintCheckInterface
{
    /**
     * @param list<KnowledgeItem> $items
     * @return list<array{item_id: string, type: string, message: string}>
     */
    public function run(array $items): array
    {
        $knownIds = [];
        foreach ($items as $item) {
            $knownIds[] = (string) $item->get('id');
        }

        $findings = [];

        foreach ($items as $item) {
            $id = (string) $item->get('id');
            $body = (string) ($item->get('body') ?? '');

            if (!preg_match_all('/\[\[([^\]]+)\]\]/', $body, $matches)) {
                continue;
            }

            foreach (array_unique($matches[1]) as $link) {
                if (!in_array($link, $knownIds, true)) {
                    $findings[] = [
                        'item_id' => $id,
                        'type' => 'broken_link',
                        'message' => "Page '{$id}' links to '[[{$link}]]' which does not exist.",
                    ];
                }
            }
        }

        return $findings;
    }
}
