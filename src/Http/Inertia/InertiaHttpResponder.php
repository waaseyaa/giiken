<?php

declare(strict_types=1);

namespace Giiken\Http\Inertia;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Cache\CacheConfigResolver;
use Waaseyaa\Foundation\Http\Inertia\InertiaFullPageRendererInterface;
use Waaseyaa\Foundation\Http\JsonApiResponseTrait;
use Waaseyaa\Inertia\InertiaResponse;

/**
 * Bridges {@see InertiaResponse} to Symfony HTTP responses for {@see \Waaseyaa\SSR\SsrPageHandler}
 * app-controller dispatch (which only handles {@see Response} today).
 */
final class InertiaHttpResponder
{
    use JsonApiResponseTrait;

    /**
     * @param array<string, mixed> $appConfig Kernel config (for {@see CacheConfigResolver}).
     */
    public function __construct(
        private readonly ?InertiaFullPageRendererInterface $fullPageRenderer,
        private readonly array $appConfig = [],
    ) {}

    public function toResponse(
        InertiaResponse $page,
        HttpRequest $request,
        AccountInterface $account,
    ): Response {
        if ($this->fullPageRenderer === null) {
            return $this->jsonApiResponse(500, [
                'jsonapi' => ['version' => '1.1'],
                'errors' => [[
                    'status' => '500',
                    'title' => 'Internal Server Error',
                    'detail' => 'Inertia full-page renderer is not configured.',
                ]],
            ]);
        }

        $pageObject = $page->toPageObject();
        $pageObject['url'] = $request->getRequestUri();

        if ($request->headers->get('X-Inertia') === 'true') {
            return $this->jsonApiResponse(200, $pageObject, [
                'X-Inertia' => 'true',
                'Vary' => 'X-Inertia',
            ]);
        }

        $html = $this->fullPageRenderer->render($pageObject);
        $response = new Response(
            $html,
            200,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );

        $cache = new CacheConfigResolver($this->appConfig);
        $maxAge = $cache->resolveRenderCacheMaxAge();
        $response->headers->set(
            'Cache-Control',
            $cache->cacheControlHeaderForRender($account, $maxAge),
        );

        return $response;
    }
}
