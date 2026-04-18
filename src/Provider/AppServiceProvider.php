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
use App\Ingestion\IngestionHandlerRegistry;
use App\Ingestion\NorthCloud\NcHitToKnowledgeItemMapper;
use Waaseyaa\NorthCloud\Sync\MapperRegistry;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherContract;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
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
