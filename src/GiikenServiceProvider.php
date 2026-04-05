<?php

declare(strict_types=1);

namespace Giiken;

use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Http\Controller\DiscoveryController;
use Giiken\Http\Controller\ManagementController;
use Giiken\Wiki\WikiLintReport;
use Symfony\Component\Routing\Route;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\WaaseyaaRouter;

final class GiikenServiceProvider extends ServiceProvider
{
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
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // Discovery routes
        $router->addRoute('giiken.discovery.index', new Route(
            '/{communitySlug}',
            ['_controller' => [DiscoveryController::class, 'index']],
            ['communitySlug' => '[a-z0-9-]+'],
        ));

        $router->addRoute('giiken.discovery.search', new Route(
            '/{communitySlug}/search',
            ['_controller' => [DiscoveryController::class, 'search']],
            ['communitySlug' => '[a-z0-9-]+'],
        ));

        $router->addRoute('giiken.discovery.ask', new Route(
            '/{communitySlug}/ask',
            ['_controller' => [DiscoveryController::class, 'ask']],
            ['communitySlug' => '[a-z0-9-]+'],
        ));

        $router->addRoute('giiken.discovery.show', new Route(
            '/{communitySlug}/item/{itemId}',
            ['_controller' => [DiscoveryController::class, 'show']],
            ['communitySlug' => '[a-z0-9-]+', 'itemId' => '.+'],
        ));

        // Management routes
        $router->addRoute('giiken.management.dashboard', new Route(
            '/{communitySlug}/manage',
            ['_controller' => [ManagementController::class, 'dashboard']],
            ['communitySlug' => '[a-z0-9-]+'],
        ));

        $router->addRoute('giiken.management.reports', new Route(
            '/{communitySlug}/manage/reports',
            ['_controller' => [ManagementController::class, 'reports']],
            ['communitySlug' => '[a-z0-9-]+'],
        ));

        $router->addRoute('giiken.management.users', new Route(
            '/{communitySlug}/manage/users',
            ['_controller' => [ManagementController::class, 'users']],
            ['communitySlug' => '[a-z0-9-]+'],
        ));

        $router->addRoute('giiken.management.ingestion', new Route(
            '/{communitySlug}/manage/ingestion',
            ['_controller' => [ManagementController::class, 'ingestion']],
            ['communitySlug' => '[a-z0-9-]+'],
        ));

        $router->addRoute('giiken.management.export', new Route(
            '/{communitySlug}/manage/export',
            ['_controller' => [ManagementController::class, 'exportPage']],
            ['communitySlug' => '[a-z0-9-]+'],
        ));
    }

    // Phase 2 ingestion handler wiring deferred until Waaseyaa DI container is ready.
    // See docs/superpowers/plans/2026-04-04-ingestion-pipeline.md Task 7.

    // Phase 3 service wiring — deferred until Waaseyaa DI container is ready:
    //
    // SearchService(Fts5SearchProvider, EmbeddingProviderInterface, KnowledgeItemAccessPolicy, KnowledgeItemRepositoryInterface)
    // QaService(SearchService, LlmProviderInterface)
    // ReportService([GovernanceSummaryReport, LanguageReport, LandBriefReport], KnowledgeItemRepositoryInterface)
    // ExportService(KnowledgeItemRepositoryInterface, EmbeddingProviderInterface, FileRepositoryInterface)
    // ImportService(CommunityRepositoryInterface, KnowledgeItemRepositoryInterface, FileRepositoryInterface)
    // MediaIngestionHandler(FileRepositoryInterface, QueueInterface) -> register with IngestionHandlerRegistry
    //
    // KnowledgeItemRepository takes optional SearchIndexerInterface for FTS indexing on save.
}
