<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Ingestion\Handler;

use Giiken\Entity\Community\Community;
use Giiken\Ingestion\Converter\FileConverterInterface;
use Giiken\Ingestion\Handler\CsvIngestionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Media\File;
use Waaseyaa\Media\FileRepositoryInterface;

#[CoversClass(CsvIngestionHandler::class)]
final class CsvIngestionHandlerTest extends TestCase
{
    #[Test]
    public function it_supports_csv(): void
    {
        $handler = $this->createHandler('');
        $this->assertTrue($handler->supports('text/csv'));
        $this->assertFalse($handler->supports('application/pdf'));
    }

    #[Test]
    public function it_converts_csv_to_markdown_table(): void
    {
        $handler = $this->createHandler("| Name | Role |\n|---|---|\n| Jane | Mayor |");
        $community = Community::make(['name' => 'Test', 'slug' => 'test']);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, "Name,Role\nJane,Mayor");

        try {
            $result = $handler->handle($tmpFile, 'text/csv', 'people.csv', $community);

            $this->assertStringContainsString('Jane', $result->markdownContent);
            $this->assertSame('text/csv', $result->mimeType);
            $this->assertArrayHasKey('row_count', $result->metadata);
            $this->assertArrayHasKey('column_names', $result->metadata);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    private function createHandler(string $converterOutput): CsvIngestionHandler
    {
        $converter = new class($converterOutput) implements FileConverterInterface {
            public function __construct(private readonly string $output) {}
            public function toMarkdown(string $filePath, string $mimeType): string { return $this->output; }
            public function supports(string $mimeType): bool { return true; }
        };

        $mediaRepo = new class implements FileRepositoryInterface {
            public function save(File $file): File { return new File(uri: 'mock-media-id', filename: $file->filename, mimeType: $file->mimeType); }
            public function load(string $uri): ?File { return null; }
            public function delete(string $uri): bool { return true; }
            public function findByOwner(int $ownerId): array { return []; }
        };

        return new CsvIngestionHandler($converter, $mediaRepo);
    }
}
