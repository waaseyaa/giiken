<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem\Source;

/**
 * Credit information — who made the work, who published it, how to cite it.
 *
 * Aligns with Schema.org `CreativeWork.creator` / `CreativeWork.publisher` and
 * Dublin Core `dc:creator` / `dc:publisher`. For Indigenous knowledge,
 * {@see Rights::$careFlags} carries the community-authority layer; this object
 * is just the human-credit record.
 */
final readonly class Attribution
{
    public function __construct(
        public ?string $creator = null,
        public ?string $publisher = null,
        public ?string $publishedAt = null,
        public ?string $citation = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            creator: self::nullableString($data['creator'] ?? null),
            publisher: self::nullableString($data['publisher'] ?? null),
            publishedAt: self::nullableString($data['published_at'] ?? null),
            citation: self::nullableString($data['citation'] ?? null),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'creator' => $this->creator,
            'publisher' => $this->publisher,
            'published_at' => $this->publishedAt,
            'citation' => $this->citation,
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
