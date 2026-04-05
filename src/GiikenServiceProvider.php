<?php

declare(strict_types=1);

namespace Giiken;

use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
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
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void {}

    /**
     * Ingestion and pipeline wiring.
     *
     * The IngestionHandlerRegistry, CompilationPipeline, and their
     * dependencies (FileConverterInterface, LlmProviderInterface,
     * EmbeddingProviderInterface, FileRepositoryInterface) should be
     * registered as singletons in the container when Waaseyaa's DI
     * container is available. Handler registration order:
     *
     * 1. MarkdownIngestionHandler (text/markdown — passthrough)
     * 2. DocumentIngestionHandler (PDF, DOCX, XLSX, PPTX — via MarkItDown)
     * 3. CsvIngestionHandler (text/csv — via MarkItDown + metadata)
     * 4. HtmlIngestionHandler (text/html — via MarkItDown)
     */
    private function registerIngestionHandlers(): void
    {
        // Wiring deferred until Waaseyaa service container is ready.
        // See docs/superpowers/plans/2026-04-04-ingestion-pipeline.md Task 7.
    }
}
