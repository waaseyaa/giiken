<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Ingestion\Handler;

use Giiken\Entity\Community\Community;
use Giiken\Ingestion\Handler\MarkdownIngestionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Media\FileRepositoryInterface;

#[CoversClass(MarkdownIngestionHandler::class)]
final class MarkdownIngestionHandlerTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__, 3) . '/fixtures';
    }

    #[Test]
    public function it_supports_markdown_mime_type(): void
    {
        $handler = new MarkdownIngestionHandler($this->createMockMediaRepo());
        $this->assertTrue($handler->supports('text/markdown'));
        $this->assertFalse($handler->supports('application/pdf'));
        $this->assertFalse($handler->supports('text/plain'));
    }

    #[Test]
    public function it_passes_markdown_content_through(): void
    {
        $handler = new MarkdownIngestionHandler($this->createMockMediaRepo());
        $community = new Community(['name' => 'Test']);

        $result = $handler->handle(
            $this->fixturesDir . '/sample.md',
            'text/markdown',
            'sample.md',
            $community,
        );

        $this->assertStringContainsString('# Sample Knowledge Item', $result->markdownContent);
        $this->assertSame('text/markdown', $result->mimeType);
        $this->assertSame('sample.md', $result->originalFilename);
    }

    #[Test]
    public function it_extracts_yaml_frontmatter_to_metadata(): void
    {
        $handler = new MarkdownIngestionHandler($this->createMockMediaRepo());
        $community = new Community(['name' => 'Test']);

        $result = $handler->handle(
            $this->fixturesDir . '/sample.md',
            'text/markdown',
            'sample.md',
            $community,
        );

        $this->assertArrayHasKey('frontmatter', $result->metadata);
        $this->assertSame('Sample Knowledge Item', $result->metadata['frontmatter']['title']);
        $this->assertSame('https://example.com/article', $result->metadata['frontmatter']['source']);
    }

    #[Test]
    public function it_strips_frontmatter_from_content(): void
    {
        $handler = new MarkdownIngestionHandler($this->createMockMediaRepo());
        $community = new Community(['name' => 'Test']);

        $result = $handler->handle(
            $this->fixturesDir . '/sample.md',
            'text/markdown',
            'sample.md',
            $community,
        );

        $this->assertStringNotContainsString('---', $result->markdownContent);
        $this->assertStringStartsWith('# Sample Knowledge Item', trim($result->markdownContent));
    }

    private function createMockMediaRepo(): FileRepositoryInterface
    {
        return new class implements FileRepositoryInterface {
            public function save(string $filePath, string $filename, string $ownerId): string
            {
                return 'mock-media-id';
            }
            public function load(string $mediaId): ?string { return null; }
            public function delete(string $mediaId): void {}
            public function findByOwner(string $ownerId): array { return []; }
        };
    }
}
