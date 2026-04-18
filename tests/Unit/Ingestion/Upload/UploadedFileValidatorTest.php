<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingestion\Upload;

use App\Ingestion\IngestionException;
use App\Ingestion\Upload\UploadedFileValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[CoversClass(UploadedFileValidator::class)]
final class UploadedFileValidatorTest extends TestCase
{
    /** @var string[] */
    private array $tempPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            @unlink($path);
        }
    }

    #[Test]
    public function rejects_file_that_exceeds_configured_size_limit(): void
    {
        $validator = new UploadedFileValidator(
            maxBytes: 3,
            allowedMimeTypes: ['text/plain'],
        );

        $upload = $this->makeUpload('notes.txt', 'hello', 'text/plain');

        $this->expectException(IngestionException::class);
        $this->expectExceptionMessage('maximum allowed size of 3 bytes');
        $validator->validate($upload);
    }

    #[Test]
    public function rejects_spoofed_client_mime_when_server_detects_plain_text(): void
    {
        $validator = new UploadedFileValidator(
            maxBytes: 1024,
            allowedMimeTypes: ['image/jpeg'],
        );

        $upload = $this->makeUpload('photo.jpg', 'plain text posing as jpeg', 'image/jpeg');

        $this->expectException(IngestionException::class);
        $this->expectExceptionMessage('text/plain');
        $validator->validate($upload);
    }

    #[Test]
    public function rejects_unsupported_detected_mime_type(): void
    {
        $validator = new UploadedFileValidator(
            maxBytes: 1024,
            allowedMimeTypes: ['text/markdown'],
        );

        $upload = $this->makeUpload('archive.zip', "PK\x03\x04 fake zip", 'application/zip');

        $this->expectException(IngestionException::class);
        $this->expectExceptionMessage('application/octet-stream');
        $validator->validate($upload);
    }

    #[Test]
    public function accepts_supported_markdown_upload_via_extension_fallback(): void
    {
        $validator = new UploadedFileValidator(
            maxBytes: 1024,
            allowedMimeTypes: ['text/markdown'],
        );

        $upload = $this->makeUpload('story.md', "# Heading\n\nBody", 'text/plain');
        $validated = $validator->validate($upload);

        self::assertSame('text/markdown', $validated->mimeType);
        self::assertSame('story.md', $validated->originalFilename);
        self::assertGreaterThan(0, $validated->sizeBytes);
    }

    #[Test]
    public function honors_configured_allowlist_when_extension_maps_to_supported_type(): void
    {
        $validator = new UploadedFileValidator(
            maxBytes: 1024,
            allowedMimeTypes: ['text/plain'],
        );

        $upload = $this->makeUpload('data.csv', "name,age\nAda,12\n", 'text/csv');

        $this->expectException(IngestionException::class);
        $this->expectExceptionMessage('text/csv');
        $validator->validate($upload);
    }

    private function makeUpload(string $originalFilename, string $contents, string $clientMimeType): UploadedFile
    {
        $path = sys_get_temp_dir() . '/giiken-upload-validator-' . uniqid('', true) . '-' . $originalFilename;
        file_put_contents($path, $contents);
        $this->tempPaths[] = $path;

        return new UploadedFile(
            path: $path,
            originalName: $originalFilename,
            mimeType: $clientMimeType,
            error: null,
            test: true,
        );
    }
}
