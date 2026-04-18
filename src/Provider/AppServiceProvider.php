<?php

declare(strict_types=1);

namespace App\Provider;

use App\Access\CommunityRoleResolver;
use App\Access\CommunityRoleResolverInterface;
use App\Access\KnowledgeItemAccessPolicy;
use App\Console\IngestFileCommand;
use App\Console\SearchReindexCommand;
use App\Console\SeedTestCommunityCommand;
use App\Pipeline\CompilationPipeline;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
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
use App\Ingestion\NorthCloud\NcHitToKnowledgeItemMapper;
use App\Ingestion\Upload\UploadValidatorInterface;
use App\Ingestion\Upload\UploadedFileValidator;
use Waaseyaa\NorthCloud\Sync\MapperRegistry;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherContract;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Media\FileRepositoryInterface;
use Waaseyaa\Media\LocalFileRepository;
use Waaseyaa\Queue\QueueInterface;
use Waaseyaa\Queue\SyncQueue;
use Waaseyaa\Search\SearchIndexerInterface;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(PsrEventDispatcherInterface::class, function (): PsrEventDispatcherInterface {
            $dispatcher = $this->resolve(SymfonyEventDispatcherContract::class);
            if (!$dispatcher instanceof PsrEventDispatcherInterface) {
                throw new \RuntimeException('Event dispatcher must implement Psr\\EventDispatcher\\EventDispatcherInterface.');
            }

            return $dispatcher;
        });

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
        $this->registerIngestionHandlers();
    }

    /**
     * Register Giiken's NorthCloud mapper into the package-owned registry.
     *
     * The {@see MapperRegistry} binding is owned by the northcloud package
     * provider; we resolve it and add our mapper so `northcloud:sync` can find
     * it.
     */
    private function registerNorthCloudMappers(): void
    {
        $defaultCommunityId = (string) (getenv('GIIKEN_NC_DEFAULT_COMMUNITY_ID') ?: '');

        if (!class_exists(MapperRegistry::class)) {
            return;
        }

        try {
            $registry = $this->resolve(MapperRegistry::class);
        } catch (\Throwable $exception) {
            $message = 'NorthCloud MapperRegistry could not be resolved during AppServiceProvider::boot(). '
                . 'NcHitToKnowledgeItemMapper was not registered, so northcloud:sync cannot map hits into Giiken entities. '
                . 'Ensure Waaseyaa\\NorthCloud\\Provider\\NorthCloudServiceProvider is present in the package manifest, then run ./bin/giiken optimize:manifest.';

            try {
                $logger = $this->resolve(LoggerInterface::class);
                if ($logger instanceof LoggerInterface) {
                    $logger->error($message, ['exception' => $exception]);
                }
            } catch (\Throwable) {
                // Logging is best-effort; the thrown exception below is the hard signal.
            }

            throw new \RuntimeException($message, previous: $exception);
        }

        foreach ($registry->all() as $mapper) {
            if ($mapper instanceof NcHitToKnowledgeItemMapper) {
                return;
            }
        }

        $registry->register(new NcHitToKnowledgeItemMapper(
            defaultCommunityId: $defaultCommunityId,
        ));
    }

    /**
     * Wire the ingestion pipeline: a single {@see IngestionHandlerRegistry}
     * containing all five file handlers, backed by a local-filesystem media
     * repository and a synchronous queue. Production will swap the queue for
     * a real backend (see waaseyaa/giiken#39 follow-ups); the file converter
     * is a shell wrapper around the optional MarkItDown venv and is only
     * invoked when a non-media upload arrives.
     */
    private function registerIngestionHandlers(): void
    {
        // Two levels up from src/Provider/ to the repo root — same
        // computation as registerInertiaViteRenderer(); getting this
        // wrong writes media files under src/storage/ instead of the
        // real storage/ directory. See giiken#90 for the sister bug.
        $projectRoot = dirname(__DIR__, 2);

        $this->singleton(FileRepositoryInterface::class, static function () use ($projectRoot): FileRepositoryInterface {
            return new LocalFileRepository($projectRoot . '/storage/media');
        });

        $this->singleton(QueueInterface::class, static fn (): QueueInterface => new SyncQueue());
        $this->singleton(MarkItDownRunnerInterface::class, static fn (): MarkItDownRunnerInterface => new ProcOpenMarkItDownRunner());

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
            $registry  = new IngestionHandlerRegistry();
            $mediaRepo = $this->resolve(FileRepositoryInterface::class);
            $queue     = $this->resolve(QueueInterface::class);
            $converter = $this->resolve(FileConverterInterface::class);

            $registry->register(new MarkdownIngestionHandler($mediaRepo));
            $registry->register(new CsvIngestionHandler($converter, $mediaRepo));
            $registry->register(new HtmlIngestionHandler($converter, $mediaRepo));
            $registry->register(new DocumentIngestionHandler($converter, $mediaRepo));
            $registry->register(new MediaIngestionHandler($mediaRepo, $queue));

            return $registry;
        });
    }

    public function boot(): void
    {
        // Register NC mappers after all providers are loaded so the package
        // MapperRegistry binding exists in both HTTP and CLI runtimes.
        $this->registerNorthCloudMappers();
    }

    public function commands(
        EntityTypeManager $entityTypeManager,
        DatabaseInterface $database,
        SymfonyEventDispatcherContract $dispatcher,
    ): array {
        return [
            new SeedTestCommunityCommand(
                $this->resolve(CommunityRepositoryInterface::class),
                $this->resolve(KnowledgeItemRepositoryInterface::class),
                $entityTypeManager,
            ),
            new SearchReindexCommand(
                $this->resolve(SearchIndexerInterface::class),
                $entityTypeManager,
            ),
            new IngestFileCommand(
                $this->resolve(CommunityRepositoryInterface::class),
                $this->resolve(IngestionHandlerRegistry::class),
                $this->resolve(CompilationPipeline::class),
            ),
        ];
    }

}
