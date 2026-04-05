<?php

declare(strict_types=1);

namespace Giiken\Entity\KnowledgeItem;

use Giiken\Entity\HasCommunity;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Search\SearchIndexableInterface;

final class KnowledgeItem extends ContentEntityBase implements HasCommunity, SearchIndexableInterface
{
    protected string $entityTypeId = 'knowledge_item';

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

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function getCommunityId(): string
    {
        return (string) ($this->get('community_id') ?? '');
    }

    public function getTitle(): string
    {
        return (string) ($this->get('title') ?? '');
    }

    public function getContent(): string
    {
        return (string) ($this->get('content') ?? '');
    }

    public function getKnowledgeType(): ?KnowledgeType
    {
        $value = $this->get('knowledge_type');

        if ($value === null) {
            return null;
        }

        return KnowledgeType::tryFrom((string) $value);
    }

    public function getAccessTier(): AccessTier
    {
        $value = $this->get('access_tier');

        return AccessTier::tryFrom((string) $value) ?? AccessTier::Members;
    }

    /**
     * @return string[]
     */
    public function getAllowedRoles(): array
    {
        return $this->decodeJsonArray($this->get('allowed_roles'));
    }

    /**
     * @return string[]
     */
    public function getAllowedUsers(): array
    {
        return $this->decodeJsonArray($this->get('allowed_users'));
    }

    /**
     * @return string[]
     */
    public function getSourceMediaIds(): array
    {
        return $this->decodeJsonArray($this->get('source_media_ids'));
    }

    public function getCompiledAt(): string
    {
        return (string) ($this->get('compiled_at') ?? '');
    }

    public function getCreatedAt(): string
    {
        return (string) ($this->get('created_at') ?? '');
    }

    public function getUpdatedAt(): string
    {
        return (string) ($this->get('updated_at') ?? '');
    }

    public function getSearchDocumentId(): string
    {
        return 'knowledge_item:' . $this->get('id');
    }

    /**
     * @return array<string, string>
     */
    public function toSearchDocument(): array
    {
        return [
            'title'   => $this->getTitle(),
            'content' => $this->getContent(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchMetadata(): array
    {
        return [
            'entity_type'    => 'knowledge_item',
            'community_id'   => $this->getCommunityId(),
            'knowledge_type' => $this->getKnowledgeType()?->value ?? '',
            'access_tier'    => $this->getAccessTier()->value,
        ];
    }

    /**
     * Render this KnowledgeItem as markdown for LLM consumption.
     *
     * Optimized for context windows: structured metadata header,
     * then full content body. No YAML frontmatter (wastes tokens).
     */
    public function toMarkdown(): string
    {
        $lines = [];
        $lines[] = "# {$this->getTitle()}";
        $lines[] = '';

        $metaParts = [];

        $knowledgeType = $this->getKnowledgeType();
        if ($knowledgeType !== null) {
            $metaParts[] = '**Type:** ' . ucfirst($knowledgeType->value);
        }

        $metaParts[] = '**Access:** ' . ucfirst($this->getAccessTier()->value);

        $compiledAt = $this->getCompiledAt();
        if ($compiledAt !== '') {
            $metaParts[] = '**Compiled:** ' . $compiledAt;
        }

        $lines[] = implode(' | ', $metaParts);
        $lines[] = '';
        $lines[] = $this->getContent();

        $sourceMediaIds = $this->getSourceMediaIds();
        if ($sourceMediaIds !== []) {
            $lines[] = '';
            $lines[] = '---';
            $lines[] = 'Sources: ' . implode(', ', $sourceMediaIds);
        }

        return implode("\n", $lines);
    }

    /**
     * @return string[]
     */
    private function decodeJsonArray(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_map('strval', $raw));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($decoded)) {
                return [];
            }

            return array_values(array_map('strval', $decoded));
        } catch (\JsonException) {
            return [];
        }
    }
}
