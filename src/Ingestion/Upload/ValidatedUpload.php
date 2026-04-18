<?php

declare(strict_types=1);

namespace App\Ingestion\Upload;

final readonly class ValidatedUpload
{
    public function __construct(
        public string $path,
        public string $originalFilename,
        public string $mimeType,
        public int $sizeBytes,
    ) {}
}
