<?php

declare(strict_types=1);

namespace Giiken\Ingestion\Handler;

use Giiken\Entity\Community\Community;
use Giiken\Ingestion\FileIngestionHandlerInterface;
use Giiken\Ingestion\IngestionException;
use Giiken\Ingestion\Job\TranscribeJob;
use Giiken\Ingestion\RawDocument;
use Waaseyaa\Media\File;
use Waaseyaa\Media\FileRepositoryInterface;
use Waaseyaa\Queue\QueueInterface;

final class MediaIngestionHandler implements FileIngestionHandlerInterface
{
    public const int MAX_FILE_SIZE = 2 * 1024 * 1024 * 1024;

    private const array SUPPORTED_MIME_TYPES = [
        'audio/mpeg',
        'audio/mp4',
        'audio/wav',
        'audio/ogg',
        'video/mp4',
        'video/quicktime',
        'video/webm',
    ];

    public function __construct(
        private readonly FileRepositoryInterface $mediaRepo,
        private readonly QueueInterface $queue,
    ) {}

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIME_TYPES, strict: true);
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

        if (filesize($filePath) > self::MAX_FILE_SIZE) {
            throw new IngestionException("File exceeds maximum allowed size of 2GB: {$filePath}");
        }

        $file = new File(
            uri: $filePath,
            filename: $originalFilename,
            mimeType: $mimeType,
        );
        $savedFile = $this->mediaRepo->save($file);
        $mediaId = $savedFile->uri;

        $this->queue->dispatch(new TranscribeJob(
            mediaId: $mediaId,
            communityId: (string) $community->id(),
            originalFilename: $originalFilename,
        ));

        return new RawDocument(
            markdownContent: '',
            mimeType: $mimeType,
            originalFilename: $originalFilename,
            mediaId: $mediaId,
            metadata: ['transcription_status' => 'pending'],
        );
    }
}
