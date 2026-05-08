<?php

declare(strict_types=1);

namespace App\Provider;

use App\Console\Handler\GiikenIngestFileHandler;
use App\Console\Handler\GiikenSeedTestCommunityHandler;
use App\Console\IngestFileCommand;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Ingestion\IngestionHandlerRegistry;
use App\Ingestion\NorthCloud\NcHitToKnowledgeItemMapper;
use App\Pipeline\CompilationPipeline;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherContract;
use Waaseyaa\CLI\ArgumentDefinition;
use Waaseyaa\CLI\ArgumentMode;
use Waaseyaa\CLI\CommandDefinition;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasNativeCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\NorthCloud\Sync\MapperRegistry;

final class AppServiceProvider extends ServiceProvider implements HasNativeCommandsInterface
{
    public function register(): void
    {
        $this->singleton(IngestFileCommand::class, function (): IngestFileCommand {
            return new IngestFileCommand(
                $this->resolve(CommunityRepositoryInterface::class),
                $this->resolve(IngestionHandlerRegistry::class),
                $this->resolve(CompilationPipeline::class),
            );
        });

        $this->singleton(GiikenIngestFileHandler::class, function (): GiikenIngestFileHandler {
            return new GiikenIngestFileHandler(
                $this->resolve(IngestFileCommand::class),
            );
        });

        $this->singleton(GiikenSeedTestCommunityHandler::class, function (): GiikenSeedTestCommunityHandler {
            return new GiikenSeedTestCommunityHandler(
                $this->resolve(CommunityRepositoryInterface::class),
                $this->resolve(KnowledgeItemRepositoryInterface::class),
                $this->resolve(EntityTypeManager::class),
            );
        });

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
                . 'Ensure Waaseyaa\\NorthCloud\\Provider\\NorthCloudServiceProvider is present in the package manifest, then run ./vendor/bin/waaseyaa optimize:manifest.';

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

    public function nativeCommands(): iterable
    {
        yield new CommandDefinition(
            name: 'giiken:ingest:file',
            description: 'Ingest a file into a community via the full compilation pipeline',
            arguments: [
                new ArgumentDefinition('community_slug', ArgumentMode::Required, 'Slug of the target community (must already exist).'),
                new ArgumentDefinition('file', ArgumentMode::Required, 'Absolute or relative path to the file to ingest.'),
            ],
            handler: [GiikenIngestFileHandler::class, 'execute'],
        );

        yield new CommandDefinition(
            name: 'giiken:seed:test-community',
            description: 'Seed a demo community (slug test-community) with sample public knowledge items',
            handler: [GiikenSeedTestCommunityHandler::class, 'execute'],
        );
    }
}
