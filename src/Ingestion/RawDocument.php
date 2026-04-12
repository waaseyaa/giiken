<?php

declare(strict_types=1);

namespace App\Ingestion;

final readonly class RawDocument
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $markdownContent,
        public string $mimeType,
        public string $originalFilename,
        public string $mediaId,
        public array  $metadata = [],
    ) {}
}
