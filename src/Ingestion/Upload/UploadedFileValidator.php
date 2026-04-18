<?php

declare(strict_types=1);

namespace App\Ingestion\Upload;

use App\Ingestion\IngestionException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadedFileValidator implements UploadValidatorInterface
{
    /** @var array<string, string> */
    private const EXTENSION_MIME_MAP = [
        'csv' => 'text/csv',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'htm' => 'text/html',
        'html' => 'text/html',
        'markdown' => 'text/markdown',
        'md' => 'text/markdown',
        'pdf' => 'application/pdf',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'svg' => 'image/svg+xml',
        'txt' => 'text/plain',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /** @var array<string, string> */
    private const MIME_ALIASES = [
        'application/csv' => 'text/csv',
        'application/x-csv' => 'text/csv',
        'application/x-pdf' => 'application/pdf',
        'audio/x-wav' => 'audio/wav',
        'text/x-csv' => 'text/csv',
    ];

    /** @var string[] */
    private const EXTENSION_FALLBACK_MIME_TYPES = [
        'application/octet-stream',
        'application/zip',
        'text/plain',
    ];

    /**
     * @param string[] $allowedMimeTypes
     */
    public function __construct(
        private readonly int $maxBytes,
        private readonly array $allowedMimeTypes,
    ) {}

    public function validate(UploadedFile $upload): ValidatedUpload
    {
        if (!$upload->isValid()) {
            throw new IngestionException($upload->getErrorMessage() ?: 'Upload failed.');
        }

        $path = $upload->getPathname();
        if ($path === '' || !is_file($path)) {
            throw new IngestionException('Uploaded file could not be read from temporary storage.');
        }

        $sizeBytes = $this->resolveSizeBytes($upload, $path);
        if ($sizeBytes > $this->maxBytes) {
            throw new IngestionException("Uploaded file exceeds the maximum allowed size of {$this->maxBytes} bytes.");
        }

        $originalFilename = (string) $upload->getClientOriginalName();
        $mimeType = $this->detectMimeType($path, $originalFilename);

        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new IngestionException("Uploaded file type is not allowed: {$mimeType}");
        }

        return new ValidatedUpload(
            path: $path,
            originalFilename: $originalFilename,
            mimeType: $mimeType,
            sizeBytes: $sizeBytes,
        );
    }

    private function resolveSizeBytes(UploadedFile $upload, string $path): int
    {
        $sizeBytes = $upload->getSize();
        if (is_int($sizeBytes) && $sizeBytes >= 0) {
            return $sizeBytes;
        }

        $fileSize = filesize($path);
        if (!is_int($fileSize)) {
            throw new IngestionException('Uploaded file size could not be determined.');
        }

        return $fileSize;
    }

    private function detectMimeType(string $path, string $originalFilename): string
    {
        $detectedMimeType = $this->normalizeMimeType($this->detectMimeTypeWithFileinfo($path));
        if ($detectedMimeType !== '' && in_array($detectedMimeType, $this->allowedMimeTypes, true)) {
            return $detectedMimeType;
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $extensionMimeType = self::EXTENSION_MIME_MAP[$extension] ?? null;
        if (
            $extensionMimeType !== null
            && in_array($extensionMimeType, $this->allowedMimeTypes, true)
            && (
                $detectedMimeType === ''
                || in_array($detectedMimeType, self::EXTENSION_FALLBACK_MIME_TYPES, true)
            )
        ) {
            return $extensionMimeType;
        }

        return $detectedMimeType !== '' ? $detectedMimeType : 'application/octet-stream';
    }

    private function detectMimeTypeWithFileinfo(string $path): string
    {
        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMimeType = $fileInfo->file($path);

        return is_string($detectedMimeType) ? $detectedMimeType : '';
    }

    private function normalizeMimeType(string $mimeType): string
    {
        $mimeType = strtolower(trim($mimeType));

        return self::MIME_ALIASES[$mimeType] ?? $mimeType;
    }
}
