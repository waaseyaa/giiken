<?php

declare(strict_types=1);

namespace App\Provider;

use App\Access\KnowledgeItemAccessPolicy;
use App\Console\SeedTestCommunityCommand;
use App\Entity\Community\Community;
use App\Entity\Community\CommunityRepository;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\KnowledgeItemRepository;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Export\ExportService;
use App\Export\ExportServiceInterface;
use App\Http\Controller\DiscoveryController;
use App\Http\Controller\HomeController;
use App\Http\Controller\ManagementController;
use App\Http\Controller\QueryApiController;
use App\Http\Controller\WebLoginController;
use App\Http\Controller\WebLogoutController;
use App\Http\Inertia\InertiaHttpResponder;
use App\Ingestion\Converter\FileConverterInterface;
use App\Ingestion\Converter\MarkItDownConverter;
use App\Ingestion\Handler\CsvIngestionHandler;
use App\Ingestion\Handler\DocumentIngestionHandler;
use App\Ingestion\Handler\HtmlIngestionHandler;
use App\Ingestion\Handler\MarkdownIngestionHandler;
use App\Ingestion\Handler\MediaIngestionHandler;
use App\Ingestion\IngestionHandlerRegistry;
use App\Pipeline\Provider\EmbeddingProviderInterface;
use App\Pipeline\Provider\LlmProviderInterface;
use App\Pipeline\Provider\NullEmbeddingProvider;
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
use App\Wiki\WikiLintReport;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as SymfonyEventDispatcherContract;
use Waaseyaa\Database\DatabaseInterface;
use Waaseyaa\Entity\EntityType;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\Asset\ViteAssetManager;
use Waaseyaa\Foundation\Http\Inertia\InertiaFullPageRendererInterface;
use Waaseyaa\EntityStorage\Connection\SingleConnectionResolver;
use Waaseyaa\EntityStorage\Driver\SqlStorageDriver;
use Waaseyaa\EntityStorage\EntityRepository as WaaseyaaEntityRepository;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\RootTemplateRenderer;
use Waaseyaa\Media\FileRepositoryInterface;
use Waaseyaa\Media\LocalFileRepository;
use Waaseyaa\Queue\QueueInterface;
use Waaseyaa\Queue\SyncQueue;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;
use Waaseyaa\Search\SearchIndexerInterface;
use Waaseyaa\Search\SearchProviderInterface;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Single-segment paths that must not be treated as community slugs (framework routes, APIs, auth).
     */
    private const string COMMUNITY_SLUG_REQUIREMENT = '(?!admin$)(?!api$)(?!login$)(?!logout$)[a-z0-9-]+';

    public function register(): void
    {
        $this->singleton(InertiaHttpResponder::class, function (): InertiaHttpResponder {
            try {
                $renderer = $this->resolve(InertiaFullPageRendererInterface::class);
            } catch (\Throwable) {
                $renderer = null;
            }

            return new InertiaHttpResponder($renderer, $this->config);
        });

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

        $this->singleton(ReportServiceInterface::class, function (): ReportServiceInterface {
            return new ReportService(
                [
                    new GovernanceSummaryReport(),
                    new LanguageReport(),
                    new LandBriefReport(),
                ],
                $this->resolve(KnowledgeItemRepositoryInterface::class),
                $this->resolve(KnowledgeItemAccessPolicy::class),
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

        $this->registerIngestionHandlers();

        $this->registerInertiaViteRenderer();
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
        $projectRoot = dirname(__DIR__);

        $this->singleton(FileRepositoryInterface::class, static function () use ($projectRoot): FileRepositoryInterface {
            return new LocalFileRepository($projectRoot . '/storage/media');
        });

        $this->singleton(QueueInterface::class, static fn (): QueueInterface => new SyncQueue());

        $this->singleton(FileConverterInterface::class, static function () use ($projectRoot): FileConverterInterface {
            return new MarkItDownConverter($projectRoot . '/storage/markitdown-venv');
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

    /**
     * Use project-root-based Vite paths (not getcwd()) so assets resolve under PHPUnit and CLI.
     */
    private function registerInertiaViteRenderer(): void
    {
        $projectRoot = dirname(__DIR__);
        $rawDev = $_ENV['VITE_DEV_SERVER'] ?? getenv('VITE_DEV_SERVER');
        $devServerUrl = is_string($rawDev) && $rawDev !== '' ? $rawDev : null;

        $assetManager = new ViteAssetManager(
            basePath: $projectRoot . '/public',
            baseUrl: '',
            devServerUrl: $devServerUrl,
        );

        // This closure is the single source of truth for Giiken's root HTML
        // shell — there is no blade/twig equivalent, and nothing else
        // re-declares the doctype/head/#app/script tags. Future readers
        // diffing against a hypothetical `resources/views/app.blade.php`
        // should not exist: there is no such file.
        //
        // Workaround for waaseyaa/inertia: framework renders
        // <script data-page="true">, but Inertia v2's client reader queries
        // for `script[data-page="app"]` (matching the el id). Rewrite the
        // attribute so the page object actually mounts.
        //
        // Remove the attribute rewrite — and consider returning to the
        // framework default `RootTemplateRenderer` constructor instead of
        // passing a custom template — once waaseyaa/framework#1227 ships.
        // Tracked as waaseyaa/giiken#66.
        $template = static function (string $scriptTag) use ($assetManager): string {
            $scriptTag = str_replace('data-page="true"', 'data-page="app"', $scriptTag);
            $assetTags = $assetManager->assetTags();

            return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                {$assetTags}
            </head>
            <body>
                <div id="app"></div>
                {$scriptTag}
            </body>
            </html>
            HTML;
        };

        $renderer = new RootTemplateRenderer(template: $template, assetManager: $assetManager);
        Inertia::setRenderer($renderer);
        Inertia::setVersion('giiken');
        $this->singleton(InertiaFullPageRendererInterface::class, static fn (): InertiaFullPageRendererInterface => $renderer);
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
        ];
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        $router->addRoute(
            'giiken.home',
            RouteBuilder::create('/')
                ->controller(HomeController::class . '::discover')
                ->methods('GET')
                ->allowAll()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.login',
            RouteBuilder::create('/login')
                ->controller(WebLoginController::class . '::showForm')
                ->methods('GET')
                ->allowAll()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.login.submit',
            RouteBuilder::create('/login')
                ->controller(WebLoginController::class . '::submit')
                ->methods('POST')
                ->allowAll()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.logout',
            RouteBuilder::create('/logout')
                ->controller(WebLogoutController::class . '::logout')
                ->methods('GET')
                ->allowAll()
                ->render()
                ->build(),
        );

        // Discovery routes (public)
        $router->addRoute(
            'giiken.discovery.index',
            RouteBuilder::create('/{communitySlug}')
                ->controller(DiscoveryController::class . '::index')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->methods('GET')
                ->allowAll()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.discovery.search',
            RouteBuilder::create('/{communitySlug}/search')
                ->controller(DiscoveryController::class . '::search')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->methods('GET')
                ->allowAll()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.discovery.ask',
            RouteBuilder::create('/{communitySlug}/ask')
                ->controller(DiscoveryController::class . '::ask')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->methods('GET')
                ->allowAll()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.discovery.show',
            RouteBuilder::create('/{communitySlug}/item/{itemId}')
                ->controller(DiscoveryController::class . '::show')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->requirement('itemId', '.+')
                ->methods('GET')
                ->allowAll()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.api.v1.ask',
            RouteBuilder::create('/api/v1/ask')
                ->controller(QueryApiController::class . '::ask')
                ->methods('POST')
                ->jsonApi()
                ->csrfExempt()
                ->allowAll()
                ->build(),
        );

        $router->addRoute(
            'giiken.api.v1.report',
            RouteBuilder::create('/api/v1/report')
                ->controller(QueryApiController::class . '::report')
                ->methods('POST')
                ->jsonApi()
                ->csrfExempt()
                ->requireAuthentication()
                ->build(),
        );

        $router->addRoute(
            'giiken.api.v1.synthesis',
            RouteBuilder::create('/api/v1/synthesis')
                ->controller(QueryApiController::class . '::saveSynthesis')
                ->methods('POST')
                ->jsonApi()
                ->csrfExempt()
                ->requireAuthentication()
                ->build(),
        );

        // Management routes (session-authenticated)
        $router->addRoute(
            'giiken.management.dashboard',
            RouteBuilder::create('/{communitySlug}/manage')
                ->controller(ManagementController::class . '::dashboard')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->methods('GET')
                ->requireAuthentication()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.management.reports',
            RouteBuilder::create('/{communitySlug}/manage/reports')
                ->controller(ManagementController::class . '::reports')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->methods('GET')
                ->requireAuthentication()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.management.users',
            RouteBuilder::create('/{communitySlug}/manage/users')
                ->controller(ManagementController::class . '::users')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->methods('GET')
                ->requireAuthentication()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.management.ingestion',
            RouteBuilder::create('/{communitySlug}/manage/ingestion')
                ->controller(ManagementController::class . '::ingestion')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->methods('GET')
                ->requireAuthentication()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.management.ingestion.upload',
            RouteBuilder::create('/{communitySlug}/manage/ingestion')
                ->controller(ManagementController::class . '::ingestUpload')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->methods('POST')
                ->requireAuthentication()
                ->render()
                ->build(),
        );

        $router->addRoute(
            'giiken.management.export.download',
            RouteBuilder::create('/{communitySlug}/manage/export/download')
                ->controller(ManagementController::class . '::exportDownload')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->methods('GET')
                ->requireAuthentication()
                ->build(),
        );

        $router->addRoute(
            'giiken.management.export',
            RouteBuilder::create('/{communitySlug}/manage/export')
                ->controller(ManagementController::class . '::exportPage')
                ->requirement('communitySlug', self::COMMUNITY_SLUG_REQUIREMENT)
                ->methods('GET')
                ->requireAuthentication()
                ->render()
                ->build(),
        );
    }
}
