<?php

declare(strict_types=1);

namespace App\Console;

use App\Entity\Community\CommunityRepositoryInterface;
use App\Ingestion\IngestionException;
use App\Ingestion\IngestionHandlerRegistry;
use App\Pipeline\CompilationPipeline;
use App\Pipeline\PipelineException;

/**
 * Symfony-free orchestration for `giiken:ingest:file`. The CLI kernel still
 * registers a waaseyaa {@see \Symfony\Component\Console\Command\Command} adapter
 * in {@see \App\Provider\AppServiceProvider::commands()} that forwards argv and
 * output into {@see self::run()}.
 *
 * v1 scope (giiken#94): synchronous, no overrides. Pipeline returns
 * {@see \App\Pipeline\CompilationPayload} (giiken#95).
 */
final class IngestFileCommand
{
    public const EXIT_SUCCESS = 0;

    public const EXIT_FAILURE = 1;

    public function __construct(
        private readonly CommunityRepositoryInterface $communityRepo,
        private readonly IngestionHandlerRegistry $registry,
        private readonly CompilationPipeline $pipeline,
    ) {}

    /**
     * @param \Closure(string): void $writeln
     */
    public function run(
        string $communitySlug,
        string $filePath,
        \Closure $writeln,
    ): int {
        if (!is_file($filePath)) {
            $writeln(sprintf('File not found: %s', $filePath));

            return self::EXIT_FAILURE;
        }
        if (!is_readable($filePath)) {
            $writeln(sprintf('File not readable: %s', $filePath));

            return self::EXIT_FAILURE;
        }

        $community = $this->communityRepo->findBySlug($communitySlug);
        if ($community === null) {
            $writeln(sprintf(
                'Community not found for slug: %s',
                $communitySlug,
            ));

            return self::EXIT_FAILURE;
        }

        $mimeType = $this->detectMimeType($filePath);
        $writeln(sprintf(
            '→ %s (%s)',
            basename($filePath),
            $mimeType,
        ));

        try {
            $rawDocument = $this->registry->handle(
                filePath: $filePath,
                mimeType: $mimeType,
                originalFilename: basename($filePath),
                community: $community,
            );
        } catch (IngestionException $e) {
            $writeln(sprintf('✗ Ingestion handler failed: %s', $e->getMessage()));

            return self::EXIT_FAILURE;
        }

        $writeln('✓ Handler produced RawDocument');

        try {
            $writeln('→ Running compilation pipeline (5 steps)…');
            $payload = $this->pipeline->compile($rawDocument, (string) $community->get('id'));
        } catch (PipelineException $e) {
            $writeln(sprintf('✗ Pipeline failed: %s', $e->getMessage()));

            return self::EXIT_FAILURE;
        }

        $writeln(sprintf(
            '✓ KnowledgeItem persisted into community "%s".',
            $communitySlug,
        ));
        $writeln(sprintf(
            '  id:    %s',
            $payload->entityUuid ?? '(not assigned)',
        ));
        $writeln(sprintf(
            '  type:  %s',
            $payload->knowledgeType?->value ?? '(unknown)',
        ));
        $writeln(sprintf(
            '  tier:  %s',
            $payload->accessTier->value,
        ));

        return self::EXIT_SUCCESS;
    }

    /**
     * Prefer extension-based detection for text formats — `finfo` reports
     * most text files as `text/plain`, which no handler supports. Fall
     * back to `finfo` for binary types (audio/video/pdf/docx).
     */
    private function detectMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $extensionMap = [
            'md'       => 'text/markdown',
            'markdown' => 'text/markdown',
            'html'     => 'text/html',
            'htm'      => 'text/html',
            'csv'      => 'text/csv',
        ];
        if (isset($extensionMap[$extension])) {
            return $extensionMap[$extension];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }

        try {
            $detected = finfo_file($finfo, $filePath);

            return is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
        } finally {
            finfo_close($finfo);
        }
    }
}
