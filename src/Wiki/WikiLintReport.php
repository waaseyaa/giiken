<?php

declare(strict_types=1);

namespace Giiken\Wiki;

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
        if (!array_key_exists('created_at', $values) || $values['created_at'] === null || $values['created_at'] === '') {
            $values['created_at'] = date('c');
        }

        if (!array_key_exists('knowledge_type', $values)) {
            $values['knowledge_type'] = 'lint_report';
        }

        $entityTypeId = $entityTypeId !== '' ? $entityTypeId : $this->entityTypeId;
        $entityKeys = $entityKeys !== [] ? $entityKeys : $this->entityKeys;

        parent::__construct($values, $entityTypeId, $entityKeys, $fieldDefinitions);
    }

    public static function fromStorage(array $values, HydrationContext $context): static
    {
        return new self(
            values: $values,
            entityTypeId: $context->entityTypeId,
            entityKeys: $context->entityKeys,
            fieldDefinitions: [],
        );
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function make(array $values): self
    {
        return new self($values);
    }

    protected function duplicateInstance(array $values): static
    {
        return static::fromStorage($values, new HydrationContext(
            entityTypeId: $this->entityTypeId,
            entityKeys: $this->entityKeys,
        ));
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
