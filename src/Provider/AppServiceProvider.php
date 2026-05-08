<?php

declare(strict_types=1);

namespace App\Provider;

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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherContract;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Event\EventDispatcherInterface as WaaseyaaEventDispatcherInterface;
use Waaseyaa\Foundation\Log\LoggerInterface;
use Waaseyaa\Foundation\ServiceProvider\Capability\HasCommandsInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Search\SearchIndexerInterface;

final class AppServiceProvider extends ServiceProvider implements HasCommandsInterface
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

    /**
     * @return list<Command>
     */
    public function commands(
        EntityTypeManager $entityTypeManager,
        DatabaseInterface $_database,
        WaaseyaaEventDispatcherInterface $_dispatcher,
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
            self::newGiikenIngestFileConsoleCommand(
                new IngestFileCommand(
                    $this->resolve(CommunityRepositoryInterface::class),
                    $this->resolve(IngestionHandlerRegistry::class),
                    $this->resolve(CompilationPipeline::class),
                ),
            ),
        ];
    }

    /**
     * Waaseyaa {@see HasCommandsInterface} registers Symfony Console commands only;
     * ingest orchestration lives in {@see IngestFileCommand} (zero Symfony imports).
     */
    private static function newGiikenIngestFileConsoleCommand(IngestFileCommand $ingest): Command
    {
        return new class($ingest) extends Command {
            public function __construct(private readonly IngestFileCommand $ingestFile)
            {
                parent::__construct('giiken:ingest:file');
                $this->setDescription('Ingest a file into a community via the full compilation pipeline');
            }

            protected function configure(): void
            {
                $this->addArgument(
                    'community-slug',
                    InputArgument::REQUIRED,
                    'Slug of the target community (must already exist).',
                );
                $this->addArgument(
                    'file',
                    InputArgument::REQUIRED,
                    'Absolute or relative path to the file to ingest.',
                );
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return $this->ingestFile->run(
                    (string) $input->getArgument('community-slug'),
                    (string) $input->getArgument('file'),
                    static function (string $line) use ($output): void {
                        $output->writeln($line);
                    },
                );
            }
        };
    }

}
