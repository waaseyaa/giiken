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
use App\Export\ExportService;
use App\Export\ExportServiceInterface;
use App\Http\Api\Ask\AskRequestValidator;
use App\Http\RateLimit\FileRequestRateLimiter;
use App\Http\RateLimit\RequestRateLimiterInterface;
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
use App\Pipeline\Provider\EmbeddingProviderInterface;
use App\Pipeline\Provider\LlmProviderInterface;
use App\Pipeline\Provider\NullEmbeddingProvider;
use App\Pipeline\Provider\AnthropicLlmProvider;
use App\Pipeline\Provider\NullLlmProvider;
use App\Query\QaService;
use App\Query\QaServiceInterface;
use App\Query\Report\GovernanceSummaryReport;
use App\Query\Report\LandBriefReport;
use App\Query\Report\LanguageReport;
use App\Query\Report\ReportService;
use App\Query\Report\ReportServiceInterface;
use App\Query\SearchService;
use App\Query\SynthesisService;
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
use Waaseyaa\Search\SearchProviderInterface;

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

        $this->singleton(EmbeddingProviderInterface::class, static fn (): EmbeddingProviderInterface => new NullEmbeddingProvider());
        $this->singleton(LlmProviderInterface::class, static function (): LlmProviderInterface {
            $provider = getenv('WAASEYAA_LLM_PROVIDER') ?: '';
            $apiKey = getenv('ANTHROPIC_API_KEY') ?: '';

            if ($provider === 'anthropic' && $apiKey !== '') {
                $model = getenv('WAASEYAA_ANTHROPIC_MODEL') ?: 'claude-sonnet-4-6';

                return new AnthropicLlmProvider(
                    new \Waaseyaa\AI\Agent\Provider\AnthropicProvider($apiKey, $model),
                );
            }

            return new NullLlmProvider();
        });
        $this->singleton(AskRequestValidator::class, function (): AskRequestValidator {
            /** @var mixed $maxQuestionLength */
            $maxQuestionLength = $this->config['api']['ask']['question_max_length'] ?? 2_000;

            return new AskRequestValidator(is_int($maxQuestionLength) ? $maxQuestionLength : 2_000);
        });
        $this->singleton(RequestRateLimiterInterface::class, function (): RequestRateLimiterInterface {
            /** @var mixed $maxAttempts */
            $maxAttempts = $this->config['api']['ask']['rate_limit']['max_attempts'] ?? 10;
            /** @var mixed $windowSeconds */
            $windowSeconds = $this->config['api']['ask']['rate_limit']['window_seconds'] ?? 60;

            return new FileRequestRateLimiter(
                storageDirectory: dirname(__DIR__, 2) . '/storage/framework/rate-limits/ask',
                maxAttempts: is_int($maxAttempts) ? $maxAttempts : 10,
                windowSeconds: is_int($windowSeconds) ? $windowSeconds : 60,
            );
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
        $this->singleton(SearchService::class, function (): SearchService {
            return new SearchService(
                $this->resolve(SearchProviderInterface::class),
                $this->resolve(EmbeddingProviderInterface::class),
                $this->resolve(KnowledgeItemAccessPolicy::class),
                $this->resolve(KnowledgeItemRepositoryInterface::class),
            );
        });

        $this->singleton(QaServiceInterface::class, function (): QaServiceInterface {
            return new QaService(
                $this->resolve(SearchService::class),
                $this->resolve(LlmProviderInterface::class),
            );
        });

        $this->singleton(ReportServiceInterface::class, function (): ReportServiceInterface {
            return new ReportService(
                [
                    new GovernanceSummaryReport(),
                    new LanguageReport(),
                    new LandBriefReport(),
                ],
                $this->resolve(KnowledgeItemRepositoryInterface::class),
                $this->resolve(KnowledgeItemAccessPolicy::class),
                $this->resolve(CommunityRoleResolverInterface::class),
            );
        });

        $this->singleton(ExportServiceInterface::class, function (): ExportServiceInterface {
            return new ExportService($this->resolve(KnowledgeItemRepositoryInterface::class));
        });

        $this->singleton(SynthesisService::class, function (): SynthesisService {
            return new SynthesisService(
                $this->resolve(KnowledgeItemRepositoryInterface::class),
                $this->resolve(KnowledgeItemAccessPolicy::class),
            );
        });

        $this->singleton(CompilationPipeline::class, function (): CompilationPipeline {
            return new CompilationPipeline(
                $this->resolve(LlmProviderInterface::class),
                $this->resolve(EmbeddingProviderInterface::class),
                $this->resolve(KnowledgeItemRepositoryInterface::class),
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
