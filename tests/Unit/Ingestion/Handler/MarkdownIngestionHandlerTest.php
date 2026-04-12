<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingestion\Handler;

use App\Entity\Community\Community;
use App\Ingestion\Handler\MarkdownIngestionHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Media\File;
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
        $community = Community::make(['name' => 'Test', 'slug' => 'test']);

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
        $community = Community::make(['name' => 'Test', 'slug' => 'test']);

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
    public function it_parses_complex_yaml_frontmatter(): void
    {
        $handler = new MarkdownIngestionHandler($this->createMockMediaRepo());
        $community = Community::make(['name' => 'Test', 'slug' => 'test']);

        $result = $handler->handle(
            $this->fixturesDir . '/sample.md',
            'text/markdown',
            'sample.md',
            $community,
        );

        $this->assertArrayHasKey('frontmatter', $result->metadata);
        $this->assertSame(['governance', 'solar'], $result->metadata['frontmatter']['tags']);
    }

    #[Test]
    public function it_strips_frontmatter_from_content(): void
    {
        $handler = new MarkdownIngestionHandler($this->createMockMediaRepo());
        $community = Community::make(['name' => 'Test', 'slug' => 'test']);

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
            public function save(File $file): File
            {
                return new File(uri: 'mock-media-id', filename: $file->filename, mimeType: $file->mimeType);
            }
            public function load(string $uri): ?File { return null; }
            public function delete(string $uri): bool { return true; }
            public function findByOwner(int $ownerId): array { return []; }
        };
    }
}
