<?php

declare(strict_types=1);

namespace Giiken;

use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Wiki\WikiLintReport;
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

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void {}

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
