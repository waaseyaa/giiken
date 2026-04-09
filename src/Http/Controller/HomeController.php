<?php

declare(strict_types=1);

namespace Giiken\Http\Controller;

use Giiken\Http\Inertia\InertiaHttpResponder;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Inertia\Inertia;

/**
 * Public landing route: Inertia {@see Discover} page (Phase 4 UX skeleton).
 */
final class HomeController
{
    public function __construct(
        private readonly ?InertiaHttpResponder $inertiaHttp = null,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function discover(
        array $params,
        array $query,
        AccountInterface $account,
        HttpRequest $httpRequest,
    ): Response {
        if ($this->inertiaHttp === null) {
            return new Response('Giiken: InertiaHttpResponder is not registered.', 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return $this->inertiaHttp->toResponse(
            Inertia::render('Discover', []),
            $httpRequest,
            $account,
        );
    }
}
