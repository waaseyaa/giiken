<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem\Source;

/**
 * Reference to the upstream work a knowledge item describes.
 *
 * Maps loosely to Schema.org `isBasedOn` / Dublin Core `dc:source`. All fields
 * are optional — a manually-authored item has no reference at all; a NorthCloud
 * hit fills every field.
 */
final readonly class SourceReference
{
    public function __construct(
        public ?string $url = null,
        public ?string $sourceName = null,
        public ?string $externalId = null,
        public ?string $crawledAt = null,
        public ?int $qualityScore = null,
        public ?string $contentType = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            url: self::nullableString($data['url'] ?? null),
            sourceName: self::nullableString($data['source_name'] ?? null),
            externalId: self::nullableString($data['external_id'] ?? null),
            crawledAt: self::nullableString($data['crawled_at'] ?? null),
            qualityScore: isset($data['quality_score']) ? (int) $data['quality_score'] : null,
            contentType: self::nullableString($data['content_type'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'url' => $this->url,
            'source_name' => $this->sourceName,
            'external_id' => $this->externalId,
            'crawled_at' => $this->crawledAt,
            'quality_score' => $this->qualityScore,
            'content_type' => $this->contentType,
        ], static fn($v): bool => $v !== null && $v !== '');
    }

    public function isEmpty(): bool
    {
        return $this->toArray() === [];
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string) $value;
    }
}
