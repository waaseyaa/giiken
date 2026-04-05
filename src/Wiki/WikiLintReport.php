<?php

declare(strict_types=1);

namespace Giiken\Wiki;

use Waaseyaa\Entity\ContentEntityBase;

final class WikiLintReport extends ContentEntityBase
{
    protected string $entityTypeId = 'wiki_lint_report';

    protected array $entityKeys = [
        'id'    => 'id',
        'uuid'  => 'uuid',
        'label' => 'title',
    ];

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values = [])
    {
        if (!isset($values['created_at'])) {
            $values['created_at'] = date('c');
        }

        if (!isset($values['knowledge_type'])) {
            $values['knowledge_type'] = 'lint_report';
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function getCommunityId(): string
    {
        return (string) ($this->get('community_id') ?? '');
    }

    /**
     * @return list<array{item_id: string, type: string, message: string}>
     */
    public function getFindings(): array
    {
        $raw = $this->get('findings');

        if (is_array($raw)) {
            /** @var list<array{item_id: string, type: string, message: string}> $raw */
            return $raw;
        }

        if (is_string($raw) && $raw !== '') {
            try {
                /** @var list<array{item_id: string, type: string, message: string}> $decoded */
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                return $decoded;
            } catch (\JsonException) {
                return [];
            }
        }

        return [];
    }

    public function getFindingCount(): int
    {
        return count($this->getFindings());
    }
}
