<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingestion\Converter;

use App\Ingestion\Converter\ConversionException;
use App\Ingestion\Converter\MarkItDownConverter;
use App\Ingestion\Converter\MarkItDownRunnerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkItDownConverter::class)]
final class MarkItDownConverterTest extends TestCase
{
    #[Test]
    public function it_supports_pdf(): void
    {
        $converter = new MarkItDownConverter('/fake/bin/markitdown');
        $this->assertTrue($converter->supports('application/pdf'));
    }

    #[Test]
    public function it_supports_docx(): void
    {
        $converter = new MarkItDownConverter('/fake/bin/markitdown');
        $this->assertTrue($converter->supports('application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
    }

    #[Test]
    public function it_supports_csv(): void
    {
        $converter = new MarkItDownConverter('/fake/bin/markitdown');
        $this->assertTrue($converter->supports('text/csv'));
    }

    #[Test]
    public function it_supports_html(): void
    {
        $converter = new MarkItDownConverter('/fake/bin/markitdown');
        $this->assertTrue($converter->supports('text/html'));
    }

    #[Test]
    public function it_does_not_support_markdown(): void
    {
        $converter = new MarkItDownConverter('/fake/bin/markitdown');
        $this->assertFalse($converter->supports('text/markdown'));
    }

    #[Test]
    public function it_does_not_support_audio(): void
    {
        $converter = new MarkItDownConverter('/fake/bin/markitdown');
        $this->assertFalse($converter->supports('audio/mpeg'));
    }

    #[Test]
    public function it_throws_when_file_does_not_exist(): void
    {
        $converter = new MarkItDownConverter('/fake/bin/markitdown');

        $this->expectException(ConversionException::class);
        $this->expectExceptionMessage('File does not exist');

        $converter->toMarkdown('/nonexistent/file.pdf', 'application/pdf');
    }

    #[Test]
    public function it_throws_when_binary_not_found(): void
    {
        $converter = new MarkItDownConverter('/nonexistent/bin/markitdown');

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'fake content');

        try {
            $this->expectException(ConversionException::class);
            $converter->toMarkdown($tmpFile, 'application/pdf');
        } finally {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function it_returns_markdown_from_runner_output(): void
    {
        $binary = $this->makeExecutableScript("#!/bin/sh\nexit 0\n");
        $tmpFile = $this->makeTempFile('fake content');
        $runner = new class implements MarkItDownRunnerInterface {
            public function run(string $binaryPath, string $filePath, int $timeoutSeconds): string
            {
                return "# Converted\n\nBody";
            }
        };

        $converter = new MarkItDownConverter($binary, $runner, 5);

        try {
            $this->assertSame("# Converted\n\nBody", $converter->toMarkdown($tmpFile, 'application/pdf'));
        } finally {
            @unlink($binary);
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function it_throws_when_runner_produces_empty_output(): void
    {
        $binary = $this->makeExecutableScript("#!/bin/sh\nexit 0\n");
        $tmpFile = $this->makeTempFile('fake content');
        $runner = new class implements MarkItDownRunnerInterface {
            public function run(string $binaryPath, string $filePath, int $timeoutSeconds): string
            {
                return "   \n";
            }
        };

        $converter = new MarkItDownConverter($binary, $runner, 5);

        try {
            $this->expectException(ConversionException::class);
            $this->expectExceptionMessage('produced empty output');
            $converter->toMarkdown($tmpFile, 'application/pdf');
        } finally {
            @unlink($binary);
            @unlink($tmpFile);
        }
    }

    private function makeExecutableScript(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'markitdown-bin-');
        file_put_contents($path, $contents);
        chmod($path, 0700);

        return $path;
    }

    private function makeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'markitdown-file-');
        file_put_contents($path, $contents);

        return $path;
    }
}
