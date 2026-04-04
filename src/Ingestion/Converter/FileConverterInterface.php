<?php

declare(strict_types=1);

namespace Giiken\Ingestion\Converter;

interface FileConverterInterface
{
    /**
     * Convert a file to markdown.
     *
     * @throws ConversionException On failure (bad file, missing binary, etc.)
     */
    public function toMarkdown(string $filePath, string $mimeType): string;

    public function supports(string $mimeType): bool;
}
