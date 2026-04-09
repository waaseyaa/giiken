<?php

declare(strict_types=1);

namespace Giiken\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Http\Inertia\InertiaHttpResponder;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Foundation\Http\Inbound\InboundHttpRequest;
use Waaseyaa\Inertia\Inertia;

final class ManagementController
{
    public function __construct(
        private readonly ?CommunityRepositoryInterface $communityRepo = null,
        private readonly ?InertiaHttpResponder $inertiaHttp = null,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function dashboard(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null) {
            return $this->page('Management/Dashboard', [
                'community' => null,
                'bootError' => 'Management services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community = $this->communityRepo->findBySlug($communitySlug);

        return $this->page('Management/Dashboard', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function reports(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null) {
            return $this->page('Management/Reports', [
                'community' => null,
                'reportTypes' => ['governance_summary', 'language_report', 'land_brief'],
                'bootError' => 'Report services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community = $this->communityRepo->findBySlug($communitySlug);

        return $this->page('Management/Reports', [
            'community'   => $community !== null ? $this->serializeCommunity($community) : null,
            'reportTypes' => ['governance_summary', 'language_report', 'land_brief'],
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function users(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null) {
            return $this->page('Management/Users', [
                'community' => null,
                'bootError' => 'User services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community = $this->communityRepo->findBySlug($communitySlug);

        return $this->page('Management/Users', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function ingestion(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null) {
            return $this->page('Management/Ingestion', [
                'community' => null,
                'bootError' => 'Ingestion services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community = $this->communityRepo->findBySlug($communitySlug);

        return $this->page('Management/Ingestion', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function exportPage(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null) {
            return $this->page('Management/Export', [
                'community' => null,
                'bootError' => 'Export/import services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community = $this->communityRepo->findBySlug($communitySlug);

        return $this->page('Management/Export', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $props
     */
    private function page(string $component, array $props, HttpRequest $httpRequest, AccountInterface $account): Response
    {
        if ($this->inertiaHttp === null) {
            return new Response('Giiken: InertiaHttpResponder is not registered.', 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return $this->inertiaHttp->toResponse(
            Inertia::render($component, $props),
            $httpRequest,
            $account,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCommunity(Community $community): array
    {
        return [
            'id'     => $community->get('id'),
            'name'   => $community->getName(),
            'slug'   => $community->getSlug(),
            'locale' => $community->getLocale(),
        ];
    }
}
