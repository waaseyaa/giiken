<?php

declare(strict_types=1);

namespace Giiken\Ingestion\Job;

use Waaseyaa\Queue\Job;

final class TranscribeJob extends Job
{
    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        public readonly string $mediaId,
        public readonly string $communityId,
        public readonly string $originalFilename,
    ) {}

    public function handle(): void
    {
        // Transcription provider not yet available.
        // Will: retrieve media, run TranscribeStep, update KnowledgeItem, set status to 'completed'.
    }

    public function failed(\Throwable $e): void
    {
        // Admin intervention expected on failure.
    }
}
