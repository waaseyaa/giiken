<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem\Source;

/**
 * Where and when a knowledge item entered the system.
 *
 * Maps to PROV-O `Activity` / `wasGeneratedBy` — the act of ingestion. Separate
 * from {@see SourceReference} which describes the upstream work itself.
 */
final readonly class SourceOrigin
{
    public function __construct(
        public OriginType $type,
        public string $ingestedAt,
        public ?string $system = null,
        public ?string $pipelineVersion = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $typeRaw = (string) ($data['type'] ?? OriginType::Manual->value);
        $type = OriginType::tryFrom($typeRaw) ?? OriginType::Manual;

        return new self(
            type: $type,
            ingestedAt: (string) ($data['ingested_at'] ?? date('c')),
            system: isset($data['system']) ? (string) $data['system'] : null,
            pipelineVersion: isset($data['pipeline_version']) ? (string) $data['pipeline_version'] : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type->value,
            'ingested_at' => $this->ingestedAt,
            'system' => $this->system,
            'pipeline_version' => $this->pipelineVersion,
        ], static fn($v): bool => $v !== null);
    }

    public static function manual(?string $ingestedAt = null): self
    {
        return new self(type: OriginType::Manual, ingestedAt: $ingestedAt ?? date('c'));
    }
}
