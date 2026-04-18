<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingestion\Converter;

use App\Ingestion\Converter\ConversionException;
use App\Ingestion\Converter\ProcOpenMarkItDownRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcOpenMarkItDownRunner::class)]
final class ProcOpenMarkItDownRunnerTest extends TestCase
{
    #[Test]
    public function it_returns_stdout_from_the_process(): void
    {
        $binary = $this->makeExecutableScript("#!/bin/sh\nprintf 'Converted output'\n");
        $file = $this->makeTempFile('input');

        try {
            $runner = new ProcOpenMarkItDownRunner();

            self::assertSame('Converted output', $runner->run($binary, $file, 5));
        } finally {
            @unlink($binary);
            @unlink($file);
        }
    }

    #[Test]
    public function it_throws_sanitized_error_when_process_fails(): void
    {
        $binary = $this->makeExecutableScript("#!/bin/sh\necho 'sensitive stderr' 1>&2\nexit 7\n");
        $file = $this->makeTempFile('input');

        try {
            $runner = new ProcOpenMarkItDownRunner();

            $this->expectException(ConversionException::class);
            $this->expectExceptionMessage('exit 7');
            $this->expectExceptionMessage('MarkItDown conversion failed');
            $this->expectExceptionMessageMatches('/^(?!.*sensitive stderr).+$/');
            $runner->run($binary, $file, 5);
        } finally {
            @unlink($binary);
            @unlink($file);
        }
    }

    #[Test]
    public function it_throws_when_process_times_out(): void
    {
        $binary = $this->makeExecutableScript("#!/bin/sh\nsleep 2\n");
        $file = $this->makeTempFile('input');

        try {
            $runner = new ProcOpenMarkItDownRunner();

            $this->expectException(ConversionException::class);
            $this->expectExceptionMessage('timed out');
            $runner->run($binary, $file, 1);
        } finally {
            @unlink($binary);
            @unlink($file);
        }
    }

    private function makeExecutableScript(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'markitdown-runner-');
        file_put_contents($path, $contents);
        chmod($path, 0700);

        return $path;
    }

    private function makeTempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'markitdown-input-');
        file_put_contents($path, $contents);

        return $path;
    }
}
