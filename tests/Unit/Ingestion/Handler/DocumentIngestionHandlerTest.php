<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingestion\Handler;

use App\Entity\Community\Community;
use App\Ingestion\Converter\FileConverterInterface;
use App\Ingestion\Handler\DocumentIngestionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Media\File;
use Waaseyaa\Media\FileRepositoryInterface;

#[CoversClass(DocumentIngestionHandler::class)]
final class DocumentIngestionHandlerTest extends TestCase
{
    #[Test]
    public function it_supports_pdf(): void
    {
        $handler = $this->createHandler('');
        $this->assertTrue($handler->supports('application/pdf'));
    }

    #[Test]
    public function it_supports_docx(): void
    {
        $handler = $this->createHandler('');
        $this->assertTrue($handler->supports('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
    }

    #[Test]
    public function it_does_not_support_markdown(): void
    {
        $handler = $this->createHandler('');
        $this->assertFalse($handler->supports('text/markdown'));
    }

    #[Test]
    public function it_converts_file_via_converter_and_returns_raw_document(): void
    {
        $handler = $this->createHandler("# Meeting Minutes\n\nCouncil discussed solar project.");
        $community = Community::make(['name' => 'Test', 'slug' => 'test']);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'fake pdf bytes');

        try {
            $result = $handler->handle($tmpFile, 'application/pdf', 'minutes.pdf', $community);

            $this->assertSame("# Meeting Minutes\n\nCouncil discussed solar project.", $result->markdownContent);
            $this->assertSame('application/pdf', $result->mimeType);
            $this->assertSame('minutes.pdf', $result->originalFilename);
            $this->assertSame('mock-media-id', $result->mediaId);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    private function createHandler(string $converterOutput): DocumentIngestionHandler
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

        return new DocumentIngestionHandler($converter, $mediaRepo);
    }
}
