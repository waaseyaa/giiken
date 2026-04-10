<?php

declare(strict_types=1);

namespace Giiken\Entity\KnowledgeItem;

use Carbon\CarbonImmutable;
use Giiken\Entity\HasCommunity;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\Hydration\HydratableFromStorageInterface;
use Waaseyaa\Entity\Hydration\HydrationContext;
use Waaseyaa\Search\SearchIndexableInterface;

final class KnowledgeItem extends ContentEntityBase implements HasCommunity, HydratableFromStorageInterface, SearchIndexableInterface
{
    protected string $entityTypeId = 'knowledge_item';

    protected array $entityKeys = [
        'id'    => 'id',
        'uuid'  => 'uuid',
        'label' => 'title',
    ];

    /**
     * @var array<string, string|array<string, mixed>>
     */
    protected array $casts = [
        'created_at'        => ['type' => 'datetime_immutable', 'domain' => 'carbon_immutable'],
        'updated_at'        => ['type' => 'datetime_immutable', 'domain' => 'carbon_immutable'],
        'compiled_at'       => ['type' => 'datetime_immutable', 'domain' => 'carbon_immutable'],
        'knowledge_type'    => KnowledgeType::class,
        'access_tier'       => AccessTier::class,
        'allowed_roles'     => 'array',
        'allowed_users'     => 'array',
        'source_media_ids'  => 'array',
    ];

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        array $values = [],
        string $entityTypeId = '',
        array $entityKeys = [],
        array $fieldDefinitions = [],
    ) {
        $values = self::sanitizeValues($values);

        if (!array_key_exists('created_at', $values) || $values['created_at'] === null || $values['created_at'] === '') {
            $values['created_at'] = date('c');
        }

        if (array_key_exists('compiled_at', $values) && $values['compiled_at'] === '') {
            unset($values['compiled_at']);
        }

        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : $this->entityTypeId;
        $entityKeys = $entityKeys !== [] ? $entityKeys : $this->entityKeys;

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }

    public static function fromStorage(array $values, HydrationContext $context): static
    {
        $entity = new self(
            values: $values,
            entityTypeId: $context->entityTypeId,
            entityKeys: $context->entityKeys,
            fieldDefinitions: [],
        );
        self::reifyJsonListCastsForStorage($entity);

        return $entity;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function make(array $values): self
    {
        $entity = new self($values);
        self::reifyJsonListCastsForStorage($entity);

        return $entity;
    }

    /**
     * After {@see ContentEntityBase} construction, internal {@see $values} may hold domain-shaped PHP
     * arrays while SQL drivers expect JSON strings for cast {@code array} fields.
     */
    private static function reifyJsonListCastsForStorage(self $entity): void
    {
        foreach (['allowed_roles', 'allowed_users', 'source_media_ids'] as $key) {
            if (!array_key_exists($key, $entity->toArray())) {
                continue;
            }
            $domain = $entity->get($key);
            if (is_array($domain)) {
                $entity->set($key, $domain);
            }
        }
    }

    protected function duplicateInstance(array $values): static
    {
        return static::fromStorage($values, new HydrationContext(
            entityTypeId: $this->entityTypeId,
            entityKeys: $this->entityKeys,
        ));
    }

    /**
     * Coerce legacy / corrupt rows so {@see $casts} and {@see get()} stay safe.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function sanitizeValues(array $values): array
    {
        foreach (['allowed_roles', 'allowed_users', 'source_media_ids'] as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $raw = $values[$key];
            if (!is_string($raw) || $raw === '') {
                continue;
            }
            try {
                json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $values[$key] = [];
            }
        }

        $tierRaw = $values['access_tier'] ?? null;
        if ($tierRaw === null || $tierRaw === '') {
            $values['access_tier'] = AccessTier::Members->value;
        } else {
            $tier = AccessTier::tryFrom((string) $tierRaw);
            $values['access_tier'] = ($tier ?? AccessTier::Members)->value;
        }

        if (isset($values['knowledge_type']) && $values['knowledge_type'] !== null && $values['knowledge_type'] !== '') {
            if (KnowledgeType::tryFrom((string) $values['knowledge_type']) === null) {
                unset($values['knowledge_type']);
            }
        }

        foreach (['updated_at'] as $key) {
            if (($values[$key] ?? null) === '') {
                unset($values[$key]);
            }
        }

        return $values;
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

        if ($value instanceof KnowledgeType) {
            return $value;
        }

        return KnowledgeType::tryFrom((string) $value);
    }

    public function getAccessTier(): AccessTier
    {
        $value = $this->get('access_tier');

        if ($value instanceof AccessTier) {
            return $value;
        }

        return AccessTier::tryFrom((string) $value) ?? AccessTier::Members;
    }

    /**
     * @return string[]
     */
    public function getAllowedRoles(): array
    {
        $raw = $this->get('allowed_roles');
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_map('strval', $raw));
    }

    /**
     * @return string[]
     */
    public function getAllowedUsers(): array
    {
        $raw = $this->get('allowed_users');
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_map('strval', $raw));
    }

    /**
     * @return string[]
     */
    public function getSourceMediaIds(): array
    {
        $raw = $this->get('source_media_ids');
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_map('strval', $raw));
    }

    public function getCompiledAt(): string
    {
        $v = $this->get('compiled_at');
        if ($v === null) {
            return '';
        }

        if ($v instanceof CarbonImmutable) {
            return $v->toIso8601String();
        }

        return (string) $v;
    }

    public function getCreatedAt(): string
    {
        $v = $this->get('created_at');
        if ($v === null) {
            return '';
        }

        if ($v instanceof CarbonImmutable) {
            return $v->toIso8601String();
        }

        return (string) $v;
    }

    public function getUpdatedAt(): string
    {
        $v = $this->get('updated_at');
        if ($v === null) {
            return '';
        }

        if ($v instanceof CarbonImmutable) {
            return $v->toIso8601String();
        }

        return (string) $v;
    }

    public function createdAt(): CarbonImmutable
    {
        $v = $this->get('created_at');
        if ($v instanceof CarbonImmutable) {
            return $v;
        }

        if ($v === null || $v === '') {
            return CarbonImmutable::now();
        }

        return CarbonImmutable::parse((string) $v);
    }

    public function updatedAt(): ?CarbonImmutable
    {
        $v = $this->get('updated_at');
        if ($v === null || $v === '') {
            return null;
        }

        if ($v instanceof CarbonImmutable) {
            return $v;
        }

        return CarbonImmutable::parse((string) $v);
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
            'title' => $this->getTitle(),
            'body'  => $this->getContent(),
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
}
