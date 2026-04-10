<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Ingestion;

use Giiken\Entity\Community\Community;
use Giiken\Ingestion\FileIngestionHandlerInterface;
use Giiken\Ingestion\IngestionException;
use Giiken\Ingestion\IngestionHandlerRegistry;
use Giiken\Ingestion\RawDocument;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IngestionHandlerRegistry::class)]
final class IngestionHandlerRegistryTest extends TestCase
{
    private IngestionHandlerRegistry $registry;
    private Community $community;

    protected function setUp(): void
    {
        $this->registry = new IngestionHandlerRegistry();
        $this->community = Community::make([
            'name' => 'Test Community',
            'slug' => 'test-community',
        ]);
    }

    #[Test]
    public function it_dispatches_to_correct_handler_by_mime_type(): void
    {
        $pdfHandler = $this->createHandler('application/pdf', 'PDF content');
        $csvHandler = $this->createHandler('text/csv', 'CSV content');

        $this->registry->register($pdfHandler);
        $this->registry->register($csvHandler);

        $result = $this->registry->handle('/tmp/test.pdf', 'application/pdf', 'test.pdf', $this->community);

        $this->assertSame('PDF content', $result->markdownContent);
    }

    #[Test]
    public function it_dispatches_csv_to_csv_handler_not_pdf(): void
    {
        $pdfHandler = $this->createHandler('application/pdf', 'PDF content');
        $csvHandler = $this->createHandler('text/csv', 'CSV content');

        $this->registry->register($pdfHandler);
        $this->registry->register($csvHandler);

        $result = $this->registry->handle('/tmp/test.csv', 'text/csv', 'test.csv', $this->community);

        $this->assertSame('CSV content', $result->markdownContent);
    }

    #[Test]
    public function it_throws_for_unsupported_mime_type(): void
    {
        $this->expectException(IngestionException::class);
        $this->expectExceptionMessage('No handler supports MIME type: video/mp4');

        $this->registry->handle('/tmp/test.mp4', 'video/mp4', 'test.mp4', $this->community);
    }

    #[Test]
    public function it_uses_first_matching_handler(): void
    {
        $handler1 = $this->createHandler('text/plain', 'First handler');
        $handler2 = $this->createHandler('text/plain', 'Second handler');

        $this->registry->register($handler1);
        $this->registry->register($handler2);

        $result = $this->registry->handle('/tmp/test.txt', 'text/plain', 'test.txt', $this->community);

        $this->assertSame('First handler', $result->markdownContent);
    }

    private function createHandler(string $supportedMime, string $returnContent): FileIngestionHandlerInterface
    {
        return new class($supportedMime, $returnContent) implements FileIngestionHandlerInterface {
            public function __construct(
                private readonly string $supportedMime,
                private readonly string $returnContent,
            ) {}

            public function supports(string $mimeType): bool
            {
                return $mimeType === $this->supportedMime;
            }

            public function handle(
                string $filePath,
                string $mimeType,
                string $originalFilename,
                Community $community,
            ): RawDocument {
                return new RawDocument(
                    markdownContent: $this->returnContent,
                    mimeType: $mimeType,
                    originalFilename: $originalFilename,
                    mediaId: 'fake-media-id',
                );
            }
        };
    }
}
