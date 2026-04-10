<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Ingestion\Handler;

use Giiken\Entity\Community\Community;
use Giiken\Ingestion\Converter\FileConverterInterface;
use Giiken\Ingestion\Handler\HtmlIngestionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Media\File;
use Waaseyaa\Media\FileRepositoryInterface;

#[CoversClass(HtmlIngestionHandler::class)]
final class HtmlIngestionHandlerTest extends TestCase
{
    #[Test]
    public function it_supports_html(): void
    {
        $handler = $this->createHandler('');
        $this->assertTrue($handler->supports('text/html'));
        $this->assertFalse($handler->supports('text/csv'));
    }

    #[Test]
    public function it_converts_html_to_markdown(): void
    {
        $handler = $this->createHandler("# Solar Panel Proposal\n\nThe township is considering...");
        $community = Community::make(['name' => 'Test', 'slug' => 'test']);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, '<h1>Test</h1>');

        try {
            $result = $handler->handle($tmpFile, 'text/html', 'page.html', $community);

            $this->assertStringContainsString('Solar Panel Proposal', $result->markdownContent);
            $this->assertSame('text/html', $result->mimeType);
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    private function createHandler(string $converterOutput): HtmlIngestionHandler
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

        return new HtmlIngestionHandler($converter, $mediaRepo);
    }
}
