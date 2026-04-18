<?php

declare(strict_types=1);

namespace App\Provider;

use App\Http\Controller\DiscoveryController;
use App\Http\Controller\HomeController;
use App\Http\Controller\ManagementController;
use App\Http\Controller\QueryApiController;
use App\Http\Controller\WebLoginController;
use App\Http\Controller\WebLogoutController;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class RoutesProvider extends ServiceProvider
{
    private const string COMMUNITY_SLUG_REQUIREMENT = '(?!admin$)(?!api$)(?!login$)(?!logout$)[a-z0-9-]+';

    public function register(): void
    {
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
                ->methods('POST')
                ->allowAll()
                ->render()
                ->build(),
        );
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
