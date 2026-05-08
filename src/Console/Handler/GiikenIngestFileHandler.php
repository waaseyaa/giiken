<?php

declare(strict_types=1);

namespace App\Console\Handler;

use App\Console\IngestFileCommand;
use Waaseyaa\CLI\CliIO;

final class GiikenIngestFileHandler
{
    public function __construct(
        private readonly IngestFileCommand $ingestFile,
    ) {}

    public function execute(CliIO $io): int
    {
        $communitySlug = $io->argument('community_slug');
        $filePath = $io->argument('file');

        if (!\is_string($communitySlug) || $communitySlug === '') {
            $io->error('Missing or invalid community_slug argument.');

            return 1;
        }

        if (!\is_string($filePath) || $filePath === '') {
            $io->error('Missing or invalid file argument.');

            return 1;
        }

        return $this->ingestFile->run(
            $communitySlug,
            $filePath,
            static function (string $line) use ($io): void {
                $io->writeln($line);
            },
        );
    }
}
