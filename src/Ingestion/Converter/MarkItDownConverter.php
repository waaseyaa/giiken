<?php

declare(strict_types=1);

namespace Giiken\Ingestion\Converter;

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
        private readonly string $venvPath,
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

        $binary = $this->venvPath . '/bin/markitdown';

        if (!file_exists($binary)) {
            throw new ConversionException("MarkItDown binary not found at: {$binary}. Run bin/setup-markitdown.sh");
        }

        $command = sprintf(
            '%s %s 2>&1',
            escapeshellarg($binary),
            escapeshellarg($filePath),
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new ConversionException(
                "MarkItDown conversion failed (exit {$exitCode}): " . implode("\n", $output)
            );
        }

        $markdown = implode("\n", $output);

        if (trim($markdown) === '') {
            throw new ConversionException("MarkItDown produced empty output for: {$filePath}");
        }

        return $markdown;
    }
}
