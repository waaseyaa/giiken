<?php

declare(strict_types=1);

namespace Giiken;

use Giiken\Access\KnowledgeItemAccessPolicy;
use Giiken\Console\SeedTestCommunityCommand;
use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepository;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepository;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Http\Controller\DiscoveryController;
use Giiken\Http\Controller\ManagementController;
use Giiken\Pipeline\Provider\EmbeddingProviderInterface;
use Giiken\Pipeline\Provider\LlmProviderInterface;
use Giiken\Pipeline\Provider\NullEmbeddingProvider;
use Giiken\Pipeline\Provider\NullLlmProvider;
use Giiken\Query\QaService;
use Giiken\Query\QaServiceInterface;
use Giiken\Query\SearchService;
use Giiken\Wiki\WikiLintReport;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Symfony\Component\Routing\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherContract;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository as WaaseyaaEntityRepository;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\Search\SearchIndexerInterface;
use Waaseyaa\Search\SearchProviderInterface;

final class GiikenServiceProvider extends ServiceProvider
{
    /** Single-segment paths that must not be treated as community slugs (framework routes, APIs). */
    private const string COMMUNITY_SLUG_REQUIREMENT = '(?!admin$)(?!api$)[a-z0-9-]+';

    public function register(): void
    {
        $this->entityType(new EntityType(
            id: 'community',
            label: 'Community',
            class: Community::class,
            keys: [
                'id'    => 'id',
                'uuid'  => 'uuid',
                'label' => 'name',
            ],
        ));

        $this->entityType(new EntityType(
            id: 'knowledge_item',
            label: 'Knowledge Item',
            class: KnowledgeItem::class,
            keys: [
                'id'    => 'id',
                'uuid'  => 'uuid',
                'label' => 'title',
            ],
        ));

        $this->entityType(new EntityType(
            id: 'wiki_lint_report',
            label: 'Wiki Lint Report',
            class: WikiLintReport::class,
            keys: [
                'id'    => 'id',
                'uuid'  => 'uuid',
                'label' => 'title',
            ],
        ));

        $this->singleton(PsrEventDispatcherInterface::class, function (): PsrEventDispatcherInterface {
            $dispatcher = $this->resolve(SymfonyEventDispatcherContract::class);
            if (!$dispatcher instanceof PsrEventDispatcherInterface) {
                throw new \RuntimeException('Event dispatcher must implement Psr\\EventDispatcher\\EventDispatcherInterface.');
            }

            return $dispatcher;
        });

        $this->singleton(EmbeddingProviderInterface::class, static fn (): EmbeddingProviderInterface => new NullEmbeddingProvider());
        $this->singleton(LlmProviderInterface::class, static fn (): LlmProviderInterface => new NullLlmProvider());
        $this->singleton(KnowledgeItemAccessPolicy::class, static fn (): KnowledgeItemAccessPolicy => new KnowledgeItemAccessPolicy());

        $this->singleton(CommunityRepositoryInterface::class, function (): CommunityRepositoryInterface {
            $etm        = $this->resolve(EntityTypeManager::class);
            $database   = $this->resolve(DatabaseInterface::class);
            $dispatcher = $this->resolve(PsrEventDispatcherInterface::class);
            $driver     = new SqlStorageDriver(new SingleConnectionResolver($database), 'id');
            $entityRepo = new WaaseyaaEntityRepository(
                $etm->getDefinition('community'),
                $driver,
                $dispatcher,
                revisionDriver: null,
                database: $database,
            );

            return new CommunityRepository($entityRepo);
        });

        $this->singleton(KnowledgeItemRepositoryInterface::class, function (): KnowledgeItemRepositoryInterface {
            $etm        = $this->resolve(EntityTypeManager::class);
            $database   = $this->resolve(DatabaseInterface::class);
            $dispatcher = $this->resolve(PsrEventDispatcherInterface::class);
            $driver     = new SqlStorageDriver(new SingleConnectionResolver($database), 'id');
            $entityRepo = new WaaseyaaEntityRepository(
                $etm->getDefinition('knowledge_item'),
                $driver,
                $dispatcher,
                revisionDriver: null,
                database: $database,
            );

            return new KnowledgeItemRepository(
                $entityRepo,
                $this->resolve(SearchIndexerInterface::class),
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
            ),
        ];
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // Discovery routes
        $router->addRoute('giiken.discovery.index', new Route(
            '/{communitySlug}',
            ['_controller' => [DiscoveryController::class, 'index']],
            ['communitySlug' => self::COMMUNITY_SLUG_REQUIREMENT],
        ));

        $router->addRoute('giiken.discovery.search', new Route(
            '/{communitySlug}/search',
            ['_controller' => [DiscoveryController::class, 'search']],
            ['communitySlug' => self::COMMUNITY_SLUG_REQUIREMENT],
        ));

        $router->addRoute('giiken.discovery.ask', new Route(
            '/{communitySlug}/ask',
            ['_controller' => [DiscoveryController::class, 'ask']],
            ['communitySlug' => self::COMMUNITY_SLUG_REQUIREMENT],
        ));

        $router->addRoute('giiken.discovery.show', new Route(
            '/{communitySlug}/item/{itemId}',
            ['_controller' => [DiscoveryController::class, 'show']],
            ['communitySlug' => self::COMMUNITY_SLUG_REQUIREMENT, 'itemId' => '.+'],
        ));

        // Management routes
        $router->addRoute('giiken.management.dashboard', new Route(
            '/{communitySlug}/manage',
            ['_controller' => [ManagementController::class, 'dashboard']],
            ['communitySlug' => self::COMMUNITY_SLUG_REQUIREMENT],
        ));

        $router->addRoute('giiken.management.reports', new Route(
            '/{communitySlug}/manage/reports',
            ['_controller' => [ManagementController::class, 'reports']],
            ['communitySlug' => self::COMMUNITY_SLUG_REQUIREMENT],
        ));

        $router->addRoute('giiken.management.users', new Route(
            '/{communitySlug}/manage/users',
            ['_controller' => [ManagementController::class, 'users']],
            ['communitySlug' => self::COMMUNITY_SLUG_REQUIREMENT],
        ));

        $router->addRoute('giiken.management.ingestion', new Route(
            '/{communitySlug}/manage/ingestion',
            ['_controller' => [ManagementController::class, 'ingestion']],
            ['communitySlug' => self::COMMUNITY_SLUG_REQUIREMENT],
        ));

        $router->addRoute('giiken.management.export', new Route(
            '/{communitySlug}/manage/export',
            ['_controller' => [ManagementController::class, 'exportPage']],
            ['communitySlug' => self::COMMUNITY_SLUG_REQUIREMENT],
        ));
    }
}
