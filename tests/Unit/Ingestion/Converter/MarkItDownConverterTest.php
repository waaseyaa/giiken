<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Ingestion\Converter;

use Giiken\Ingestion\Converter\ConversionException;
use Giiken\Ingestion\Converter\MarkItDownConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkItDownConverter::class)]
final class MarkItDownConverterTest extends TestCase
{
    #[Test]
    public function it_supports_pdf(): void
    {
        $converter = new MarkItDownConverter('/fake/venv');
        $this->assertTrue($converter->supports('application/pdf'));
    }

    #[Test]
    public function it_supports_docx(): void
    {
        $converter = new MarkItDownConverter('/fake/venv');
        $this->assertTrue($converter->supports('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
    }

    #[Test]
    public function it_supports_csv(): void
    {
        $converter = new MarkItDownConverter('/fake/venv');
        $this->assertTrue($converter->supports('text/csv'));
    }

    #[Test]
    public function it_supports_html(): void
    {
        $converter = new MarkItDownConverter('/fake/venv');
        $this->assertTrue($converter->supports('text/html'));
    }

    #[Test]
    public function it_does_not_support_markdown(): void
    {
        $converter = new MarkItDownConverter('/fake/venv');
        $this->assertFalse($converter->supports('text/markdown'));
    }

    #[Test]
    public function it_does_not_support_audio(): void
    {
        $converter = new MarkItDownConverter('/fake/venv');
        $this->assertFalse($converter->supports('audio/mpeg'));
    }

    #[Test]
    public function it_throws_when_file_does_not_exist(): void
    {
        $converter = new MarkItDownConverter('/fake/venv');

        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('File does not exist');

        $converter->toMarkdown('/nonexistent/file.pdf', 'application/pdf');
    }

    #[Test]
    public function it_throws_when_binary_not_found(): void
    {
        $converter = new MarkItDownConverter('/nonexistent/venv');

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'fake content');

        try {
            $this->expectException(ConversionException::class);
            $converter->toMarkdown($tmpFile, 'application/pdf');
        } finally {
            unlink($tmpFile);
        }
    }
}
