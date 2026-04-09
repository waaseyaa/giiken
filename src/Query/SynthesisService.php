<?php

declare(strict_types=1);

namespace Giiken\Query;

use Giiken\Access\KnowledgeItemAccessPolicy;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use InvalidArgumentException;
use RuntimeException;
use Waaseyaa\Access\AccountInterface;

final class SynthesisService
{
    public function __construct(
        private readonly KnowledgeItemRepositoryInterface $items,
        private readonly KnowledgeItemAccessPolicy $accessPolicy,
    ) {}

    /**
     * @param string[] $citedItemIds
     *
     * @return array{id: string, uuid: string, title: string}
     */
    public function saveFromQa(
        string $communityId,
        string $title,
        string $content,
        array $citedItemIds,
        AccountInterface $account,
    ): array {
        $title   = trim($title);
        $content = trim($content);
        if ($title === '' || $content === '') {
            throw new InvalidArgumentException('Title and content are required.');
        }

        $citedItemIds = array_values(array_unique(array_filter($citedItemIds, static fn (string $id): bool => $id !== '')));

        /** @var KnowledgeItem[] $cited */
        $cited = [];
        foreach ($citedItemIds as $id) {
            $item = $this->items->find($id);
            if ($item === null || $item->getCommunityId() !== $communityId) {
                throw new RuntimeException("Unknown or out-of-scope knowledge item: {$id}");
            }
            if (!$this->accessPolicy->access($item, 'view', $account)->isAllowed()) {
                throw new RuntimeException("Not allowed to cite item: {$id}");
            }
            $cited[] = $item;
        }

        $cap = SynthesisAccessCapper::cap($cited);

        $uuid = self::randomUuidV4();

        $item = new KnowledgeItem([
            'uuid'           => $uuid,
            'title'          => $title,
            'content'        => $content,
            'knowledge_type' => KnowledgeType::Synthesis->value,
            'community_id'   => $communityId,
            'access_tier'    => $cap['access_tier'],
            'allowed_roles'  => $cap['allowed_roles'] === [] ? null : json_encode($cap['allowed_roles'], JSON_THROW_ON_ERROR),
            'allowed_users'  => $cap['allowed_users'] === [] ? null : json_encode($cap['allowed_users'], JSON_THROW_ON_ERROR),
            'compiled_at'    => date('c'),
        ]);
        $item->enforceIsNew(true);

        $this->items->save($item);

        return [
            'id'    => (string) $item->get('id'),
            'uuid'  => $uuid,
            'title' => $title,
        ];
    }

    private static function randomUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
