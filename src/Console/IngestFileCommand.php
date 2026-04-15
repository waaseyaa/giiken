<?php

declare(strict_types=1);

namespace App\Console;

use App\Entity\Community\CommunityRepositoryInterface;
use App\Ingestion\IngestionException;
use App\Ingestion\IngestionHandlerRegistry;
use App\Pipeline\CompilationPipeline;
use App\Pipeline\PipelineException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run a real file end-to-end through the ingestion + compilation pipeline
 * and persist a `KnowledgeItem` in the given community.
 *
 * v1 scope (giiken#94): synchronous, no overrides. Pipeline now returns a
 * `CompilationPayload` (giiken#95); operator-facing overrides
 * (`--type`, `--access`, `--dry-run`) are a follow-up PR.
 */
#[AsCommand(
    name: 'giiken:ingest:file',
    description: 'Ingest a file into a community via the full compilation pipeline',
)]
final class IngestFileCommand extends Command
{
    public function __construct(
        private readonly CommunityRepositoryInterface $communityRepo,
        private readonly IngestionHandlerRegistry $registry,
        private readonly CompilationPipeline $pipeline,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'community-slug',
                InputArgument::REQUIRED,
                'Slug of the target community (must already exist).',
            )
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Absolute or relative path to the file to ingest.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $slug = (string) $input->getArgument('community-slug');
        $filePath = (string) $input->getArgument('file');

        if (!is_file($filePath)) {
            $output->writeln(sprintf('<error>File not found: %s</error>', $filePath));

            return Command::FAILURE;
        }
        if (!is_readable($filePath)) {
            $output->writeln(sprintf('<error>File not readable: %s</error>', $filePath));

            return Command::FAILURE;
        }

        $community = $this->communityRepo->findBySlug($slug);
        if ($community === null) {
            $output->writeln(sprintf(
                '<error>Community not found for slug: %s</error>',
                $slug,
            ));

            return Command::FAILURE;
        }

        $mimeType = $this->detectMimeType($filePath);
        $output->writeln(sprintf(
            '<comment>→ %s (%s)</comment>',
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
            $output->writeln(sprintf('<error>✗ Ingestion handler failed: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln('<info>✓ Handler produced RawDocument</info>');

        try {
            $output->writeln('<comment>→ Running compilation pipeline (5 steps)…</comment>');
            $payload = $this->pipeline->compile($rawDocument, (string) $community->get('id'));
        } catch (PipelineException $e) {
            $output->writeln(sprintf('<error>✗ Pipeline failed: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln(sprintf(
            '<info>✓ KnowledgeItem persisted into community "%s".</info>',
            $slug,
        ));
        $output->writeln(sprintf(
            '  id:    <info>%s</info>',
            $payload->entityUuid ?? '(not assigned)',
        ));
        $output->writeln(sprintf(
            '  type:  <info>%s</info>',
            $payload->knowledgeType?->value ?? '(unknown)',
        ));
        $output->writeln(sprintf(
            '  tier:  <info>%s</info>',
            $payload->accessTier->value,
        ));

        return Command::SUCCESS;
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

        $detected = finfo_file($finfo, $filePath);

        return is_string($detected) && $detected !== '' ? $detected : 'application/octet-stream';
    }
}
