<?php

declare(strict_types=1);

namespace App\Ingestion\Handler;

use App\Entity\Community\Community;
use App\Ingestion\FileIngestionHandlerInterface;
use App\Ingestion\IngestionException;
use App\Ingestion\RawDocument;
use Waaseyaa\Media\File;
use Waaseyaa\Media\FileRepositoryInterface;

final class MarkdownIngestionHandler implements FileIngestionHandlerInterface
{
    public function __construct(
        private readonly FileRepositoryInterface $mediaRepo,
    ) {}

    public function supports(string $mimeType): bool
    {
        return $mimeType === 'text/markdown';
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

        $raw = file_get_contents($filePath);

        if ($raw === false) {
            throw new IngestionException("Failed to read file: {$filePath}");
        }

        $frontmatter = [];
        $content = $raw;

        if (preg_match('/\A---\n(.+?)\n---\n(.*)\z/s', $raw, $matches)) {
            $frontmatter = $this->parseYamlFrontmatter($matches[1]);
            $content = $matches[2];
        }

        $content = $this->convertObsidianCallouts($content);

        $file = new File(
            uri: $filePath,
            filename: $originalFilename,
            mimeType: $mimeType,
        );
        $savedFile = $this->mediaRepo->save($file);
        $mediaId = $savedFile->uri;

        $metadata = [];
        if ($frontmatter !== []) {
            $metadata['frontmatter'] = $frontmatter;
        }

        return new RawDocument(
            markdownContent: trim($content),
            mimeType: $mimeType,
            originalFilename: $originalFilename,
            mediaId: $mediaId,
            metadata: $metadata,
        );
    }

    /**
     * Minimal YAML subset for markdown frontmatter (scalars + indented lists).
     * Avoids Symfony Yaml so the ingest CLI path stays free of `Symfony\` imports.
     *
     * @return array<string, mixed>
     */
    private function parseYamlFrontmatter(string $yaml): array
    {
        $lines = preg_split('/\R/', $yaml) ?: [];
        $result = [];
        $n = count($lines);

        for ($i = 0; $i < $n; ++$i) {
            $trim = trim($lines[$i]);
            if ($trim === '' || str_starts_with($trim, '#')) {
                continue;
            }

            if (!preg_match('/^([A-Za-z0-9_-]+)\s*:\s*(.*)$/', $trim, $m)) {
                continue;
            }

            $key = $m[1];
            $rest = trim($m[2]);

            if ($rest === '') {
                $items = [];
                for ($j = $i + 1; $j < $n; ++$j) {
                    $lineJ = rtrim($lines[$j]);
                    if ($lineJ === '') {
                        continue;
                    }
                    if (preg_match('/^\s+-\s+(.+)$/', $lineJ, $mm)) {
                        $items[] = $this->parseYamlScalar(trim($mm[1]));
                        $i = $j;

                        continue;
                    }

                    break;
                }

                if ($items !== []) {
                    $result[$key] = $items;
                }

                continue;
            }

            $result[$key] = $this->parseYamlScalar($rest);
        }

        return $result;
    }

    private function parseYamlScalar(string $value): string|int|float|bool
    {
        $value = trim($value);

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if (preg_match('/^"(.*)"$/s', $value, $m) || preg_match('/^\'(.*)\'$/s', $value, $m)) {
            return $m[1];
        }

        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }

        return $value;
    }

    private function convertObsidianCallouts(string $content): string
    {
        return preg_replace(
            '/^>\s*\[!(\w+)\]\s*(.*)$/m',
            '> **\1:** \2',
            $content,
        ) ?? $content;
    }
}
