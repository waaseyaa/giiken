<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem\Source;

/**
 * Structured provenance for a knowledge item.
 *
 * Four layered concerns:
 *
 * - {@see SourceOrigin}    — where and when the item entered the system
 * - {@see SourceReference} — the upstream work this item describes (optional)
 * - {@see Attribution}     — human credit (creator, publisher, citation)
 * - {@see Rights}          — copyright status, license, consents, TK Labels, CARE flags
 *
 * Stored as a single JSON column on `knowledge_item.source`, with hot fields
 * mirrored to indexed columns (`source_origin_type`, `source_reference_url`,
 * `source_ingested_at`, `rights_license`) for fast filtering.
 *
 * See `docs/architecture/knowledge-item-source.md` for the full rationale,
 * industry-standard alignment, and CARE / TK Label scope.
 */
final readonly class KnowledgeItemSource
{
    public function __construct(
        public SourceOrigin $origin,
        public SourceReference $reference,
        public Attribution $attribution,
        public Rights $rights,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $origin = isset($data['origin']) && is_array($data['origin'])
            ? SourceOrigin::fromArray($data['origin'])
            : SourceOrigin::manual();

        $reference = isset($data['reference']) && is_array($data['reference'])
            ? SourceReference::fromArray($data['reference'])
            : new SourceReference();

        $attribution = isset($data['attribution']) && is_array($data['attribution'])
            ? Attribution::fromArray($data['attribution'])
            : new Attribution();

        $rights = isset($data['rights']) && is_array($data['rights'])
            ? Rights::fromArray($data['rights'])
            : Rights::default();

        return new self($origin, $reference, $attribution, $rights);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = ['origin' => $this->origin->toArray()];

        if (!$this->reference->isEmpty()) {
            $out['reference'] = $this->reference->toArray();
        }
        if (!$this->attribution->isEmpty()) {
            $out['attribution'] = $this->attribution->toArray();
        }
        $out['rights'] = $this->rights->toArray();

        return $out;
    }

    /**
     * Indexed column values mirrored from the source JSON. Written by the
     * entity-save path so SQL indexes stay in sync.
     *
     * @return array{source_origin_type: string, source_reference_url: ?string, source_ingested_at: string, rights_license: ?string}
     */
    public function indexedColumns(): array
    {
        return [
            'source_origin_type' => $this->origin->type->value,
            'source_reference_url' => $this->reference->url,
            'source_ingested_at' => $this->origin->ingestedAt,
            'rights_license' => $this->rights->license,
        ];
    }

    /** Default source for manually-created items and the backfill migration. */
    public static function manualDefault(?string $ingestedAt = null): self
    {
        return new self(
            origin: SourceOrigin::manual($ingestedAt),
            reference: new SourceReference(),
            attribution: new Attribution(),
            rights: Rights::default(),
        );
    }
}
