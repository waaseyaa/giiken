<?php

declare(strict_types=1);

namespace App\Ingestion;

use App\Entity\Community\Community;

final class IngestionHandlerRegistry
{
    /** @var FileIngestionHandlerInterface[] */
    private array $handlers = [];

    public function register(FileIngestionHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    public function handle(
        string $filePath,
        string $mimeType,
        string $originalFilename,
        Community $community,
    ): RawDocument {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($mimeType)) {
                return $handler->handle($filePath, $mimeType, $originalFilename, $community);
            }
        }

        throw new IngestionException("No handler supports MIME type: {$mimeType}");
    }
}
