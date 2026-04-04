<?php

declare(strict_types=1);

namespace Giiken\Entity\KnowledgeItem;

use Giiken\Entity\HasCommunity;
use Waaseyaa\Entity\ContentEntityBase;

final class KnowledgeItem extends ContentEntityBase implements HasCommunity
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
