<?php

declare(strict_types=1);

namespace App\Wiki;

use Carbon\CarbonImmutable;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\Hydration\HydratableFromStorageInterface;
use Waaseyaa\Entity\Hydration\HydrationContext;

final class WikiLintReport extends ContentEntityBase implements HydratableFromStorageInterface
{
    protected string $entityTypeId = 'wiki_lint_report';

    protected array $entityKeys = [
        'id'    => 'id',
        'uuid'  => 'uuid',
        'label' => 'title',
    ];

    /**
     * @var array<string, string|array<string, mixed>>
     */
    protected array $casts = [
        'created_at' => ['type' => 'datetime_immutable', 'domain' => 'carbon_immutable'],
        'updated_at' => ['type' => 'datetime_immutable', 'domain' => 'carbon_immutable'],
        'findings'   => 'array',
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
        $values = self::sanitizeFindingsForCasts($values);

        if (!array_key_exists('created_at', $values) || $values['created_at'] === null || $values['created_at'] === '') {
            $values['created_at'] = date('c');
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
        self::reifyFindingsCastForStorage($entity);

        return $entity;
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function make(array $values): self
    {
        $entity = new self($values);
        self::reifyFindingsCastForStorage($entity);

        return $entity;
    }

    private static function reifyFindingsCastForStorage(self $entity): void
    {
        if (!array_key_exists('findings', $entity->toArray())) {
            return;
        }
        $findings = $entity->get('findings');
        if (is_array($findings)) {
            $entity->set('findings', $findings);
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
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function sanitizeFindingsForCasts(array $values): array
    {
        if (!array_key_exists('findings', $values)) {
            return $values;
        }
        $raw = $values['findings'];
        if ($raw === null || is_array($raw)) {
            return $values;
        }
        if (!is_string($raw) || $raw === '') {
            $values['findings'] = [];

            return $values;
        }
        try {
            json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $values['findings'] = [];
        }

        return $values;
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

        if (!is_array($raw)) {
            return [];
        }

        /** @var list<array{item_id: string, type: string, message: string}> $raw */
        return array_values($raw);
    }

    public function getFindingCount(): int
    {
        return count($this->getFindings());
    }
}
