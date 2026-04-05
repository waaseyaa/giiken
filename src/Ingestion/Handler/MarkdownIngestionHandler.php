<?php

declare(strict_types=1);

namespace Giiken\Ingestion\Handler;

use Giiken\Entity\Community\Community;
use Giiken\Ingestion\FileIngestionHandlerInterface;
use Giiken\Ingestion\IngestionException;
use Giiken\Ingestion\RawDocument;
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

    /** @return array<string, mixed> */
    private function parseYamlFrontmatter(string $yaml): array
    {
        $result = [];
        foreach (explode("\n", $yaml) as $line) {
            if (preg_match('/^(\w+):\s*"?(.+?)"?\s*$/', $line, $m)) {
                $result[$m[1]] = $m[2];
            }
        }
        return $result;
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
