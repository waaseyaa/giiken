<?php

declare(strict_types=1);

namespace App\Wiki\Check;

use App\Entity\KnowledgeItem\KnowledgeItem;

final class OrphanPageCheck implements LintCheckInterface
{
    /**
     * @param list<KnowledgeItem> $items
     * @return list<array{item_id: string, type: string, message: string}>
     */
    public function run(array $items): array
    {
        $allIds = [];
        $linkedIds = [];

        foreach ($items as $item) {
            $id = (string) $item->get('id');
            $allIds[] = $id;

            $body = (string) ($item->get('body') ?? '');
            if (preg_match_all('/\[\[([^\]]+)\]\]/', $body, $matches)) {
                foreach ($matches[1] as $link) {
                    $linkedIds[] = $link;
                }
            }
        }

        $linkedIds = array_unique($linkedIds);
        $findings = [];

        foreach ($allIds as $id) {
            if (!in_array($id, $linkedIds, true)) {
                $findings[] = [
                    'item_id' => $id,
                    'type' => 'orphan_page',
                    'message' => "Page '{$id}' has no inbound links from other pages.",
                ];
            }
        }

        return $findings;
    }
}
