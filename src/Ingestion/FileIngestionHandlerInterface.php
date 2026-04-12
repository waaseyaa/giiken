<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Entity\Community\Community;

interface FileIngestionHandlerInterface
{
    public function supports(string $mimeType): bool;

    /**
     * @param string $filePath Path to the uploaded file on disk
     * @param string $mimeType MIME type of the uploaded file
     * @param string $originalFilename Original filename from the upload
     * @param Community $community Target community
     * @throws IngestionException On handler failure
     */
    public function handle(
        string $filePath,
        string $mimeType,
        string $originalFilename,
        Community $community,
    ): RawDocument;
}
