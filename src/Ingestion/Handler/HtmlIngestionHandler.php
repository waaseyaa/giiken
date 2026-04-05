<?php

declare(strict_types=1);

namespace Giiken\Ingestion\Handler;

use Giiken\Entity\Community\Community;
use Giiken\Ingestion\Converter\FileConverterInterface;
use Giiken\Ingestion\FileIngestionHandlerInterface;
use Giiken\Ingestion\IngestionException;
use Giiken\Ingestion\RawDocument;
use Waaseyaa\Media\File;
use Waaseyaa\Media\FileRepositoryInterface;

final class HtmlIngestionHandler implements FileIngestionHandlerInterface
{
    public function __construct(
        private readonly FileConverterInterface $converter,
        private readonly FileRepositoryInterface $mediaRepo,
    ) {}

    public function supports(string $mimeType): bool
    {
        return $mimeType === 'text/html';
    }

    public function handle(
        string $filePath,
        string $mimeType,
        string $originalFilename,
        Community $community,
    ): RawDocument {
        if (!file_exists($filePath)) {
            throw new IngestionException("File does not exist: {$filePath}");
        }

        $file = new File(uri: $filePath, filename: $originalFilename, mimeType: $mimeType);
        $savedFile = $this->mediaRepo->save($file);
        $markdown = $this->converter->toMarkdown($filePath, $mimeType);

        return new RawDocument(
            markdownContent: $markdown,
            mimeType: $mimeType,
            originalFilename: $originalFilename,
            mediaId: $savedFile->uri,
        );
    }
}
