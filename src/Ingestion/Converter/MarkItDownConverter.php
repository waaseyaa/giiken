<?php

declare(strict_types=1);

namespace App\Ingestion\Converter;

final class MarkItDownConverter implements FileConverterInterface
{
    private const SUPPORTED_MIMES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/csv',
        'text/html',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint',
    ];

    public function __construct(
        private readonly string $binaryPath,
        private readonly ?MarkItDownRunnerInterface $runner = null,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function supports(string $mimeType): bool
    {
        return in_array($mimeType, self::SUPPORTED_MIMES, true);
    }

    public function toMarkdown(string $filePath, string $mimeType): string
    {
        if (!file_exists($filePath)) {
            throw new ConversionException("File does not exist: {$filePath}");
        }

        if (!file_exists($this->binaryPath) || !is_executable($this->binaryPath)) {
            throw new ConversionException("MarkItDown binary not found or not executable at: {$this->binaryPath}. Run bin/setup-markitdown.sh");
        }

        $markdown = ($this->runner ?? new ProcOpenMarkItDownRunner())->run(
            $this->binaryPath,
            $filePath,
            $this->timeoutSeconds,
        );

        if (trim($markdown) === '') {
            throw new ConversionException("MarkItDown produced empty output for: {$filePath}");
        }

        return $markdown;
    }
}
