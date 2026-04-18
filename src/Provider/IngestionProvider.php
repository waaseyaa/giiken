<?php

declare(strict_types=1);

namespace App\Provider;

use App\Ingestion\Converter\FileConverterInterface;
use App\Ingestion\Converter\MarkItDownConverter;
use App\Ingestion\Converter\MarkItDownRunnerInterface;
use App\Ingestion\Converter\ProcOpenMarkItDownRunner;
use App\Ingestion\Handler\CsvIngestionHandler;
use App\Ingestion\Handler\DocumentIngestionHandler;
use App\Ingestion\Handler\HtmlIngestionHandler;
use App\Ingestion\Handler\MarkdownIngestionHandler;
use App\Ingestion\Handler\MediaIngestionHandler;
use App\Ingestion\IngestionHandlerRegistry;
use App\Ingestion\Upload\UploadValidatorInterface;
use App\Ingestion\Upload\UploadedFileValidator;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Media\FileRepositoryInterface;
use Waaseyaa\Media\LocalFileRepository;
use Waaseyaa\Queue\QueueInterface;
use Waaseyaa\Queue\SyncQueue;

final class IngestionProvider extends ServiceProvider
{
    public function register(): void
    {
        $projectRoot = dirname(__DIR__, 2);

        $this->singleton(FileRepositoryInterface::class, static function () use ($projectRoot): FileRepositoryInterface {
            return new LocalFileRepository($projectRoot . '/storage/media');
        });

        $this->singleton(QueueInterface::class, static fn (): QueueInterface => new SyncQueue());
        $this->singleton(MarkItDownRunnerInterface::class, static fn (): MarkItDownRunnerInterface => new ProcOpenMarkItDownRunner());

        $this->singleton(UploadValidatorInterface::class, function (): UploadValidatorInterface {
            /** @var mixed $maxBytes */
            $maxBytes = $this->config['upload_max_bytes'] ?? 0;
            /** @var mixed $allowedMimeTypes */
            $allowedMimeTypes = $this->config['upload_allowed_mime_types'] ?? [];

            return new UploadedFileValidator(
                maxBytes: is_int($maxBytes) ? $maxBytes : 0,
                allowedMimeTypes: is_array($allowedMimeTypes)
                    ? array_values(array_filter($allowedMimeTypes, static fn (mixed $value): bool => is_string($value) && $value !== ''))
                    : [],
            );
        });

        $this->singleton(FileConverterInterface::class, function () use ($projectRoot): FileConverterInterface {
            /** @var mixed $binaryPath */
            $binaryPath = $this->config['ingestion']['markitdown_binary'] ?? ($projectRoot . '/storage/markitdown-venv/bin/markitdown');
            /** @var mixed $timeoutSeconds */
            $timeoutSeconds = $this->config['ingestion']['command_timeout_seconds'] ?? 30;

            return new MarkItDownConverter(
                binaryPath: is_string($binaryPath) ? $binaryPath : ($projectRoot . '/storage/markitdown-venv/bin/markitdown'),
                runner: $this->resolve(MarkItDownRunnerInterface::class),
                timeoutSeconds: is_int($timeoutSeconds) ? $timeoutSeconds : 30,
            );
        });

        $this->singleton(IngestionHandlerRegistry::class, function (): IngestionHandlerRegistry {
            $registry = new IngestionHandlerRegistry();
            $mediaRepo = $this->resolve(FileRepositoryInterface::class);
            $queue = $this->resolve(QueueInterface::class);
            $converter = $this->resolve(FileConverterInterface::class);

            $registry->register(new MarkdownIngestionHandler($mediaRepo));
            $registry->register(new CsvIngestionHandler($converter, $mediaRepo));
            $registry->register(new HtmlIngestionHandler($converter, $mediaRepo));
            $registry->register(new DocumentIngestionHandler($converter, $mediaRepo));
            $registry->register(new MediaIngestionHandler($mediaRepo, $queue));

            return $registry;
        });
    }
}
