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

final class DocumentIngestionHandler implements FileIngestionHandlerInterface
{
    private const SUPPORTED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint',
    ];

    public function __construct(
        private readonly FileConverterInterface $converter,
        private readonly FileRepositoryInterface $mediaRepo,
    ) {}

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIMES, true);
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
