<?php

declare(strict_types=1);

namespace App\Ingestion\Converter;

interface MarkItDownRunnerInterface
{
    /**
     * @throws ConversionException
     */
    public function run(string $binaryPath, string $filePath, int $timeoutSeconds): string;
}
