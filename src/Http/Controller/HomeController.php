<?php

declare(strict_types=1);

namespace Giiken\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Http\Inertia\InertiaHttpResponder;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Inertia\Inertia;

/**
 * Public landing route: Inertia Discover page listing communities.
 */
final class HomeController
{
    public function __construct(
        private readonly ?InertiaHttpResponder $inertiaHttp = null,
        private readonly ?CommunityRepositoryInterface $communityRepo = null,
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

        $communities = $this->communityRepo === null
            ? []
            : array_map(
                static fn (Community $c): array => [
                    'id'     => $c->id(),
                    'name'   => $c->name(),
                    'slug'   => $c->slug(),
                    'locale' => $c->locale(),
                ],
                $this->communityRepo->findAll(),
            );

        return $this->inertiaHttp->toResponse(
            Inertia::render('Discover', ['communities' => $communities]),
            $httpRequest,
            $account,
        );
    }
}
