<?php

declare(strict_types=1);

namespace Giiken\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;

final class ManagementController
{
    public function __construct(
        private readonly ?CommunityRepositoryInterface $communityRepo = null,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function dashboard(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): InertiaResponse
    {
        if ($this->communityRepo === null) {
            return Inertia::render('Management/Dashboard', [
                'community' => null,
                'bootError' => 'Management services are not configured yet.',
            ]);
        }

        $communitySlug = (string) ($params['communitySlug'] ?? '');
        $community = $this->communityRepo->findBySlug($communitySlug);
        return Inertia::render('Management/Dashboard', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function reports(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): InertiaResponse
    {
        if ($this->communityRepo === null) {
            return Inertia::render('Management/Reports', [
                'community' => null,
                'reportTypes' => ['governance_summary', 'language_report', 'land_brief'],
                'bootError' => 'Report services are not configured yet.',
            ]);
        }

        $communitySlug = (string) ($params['communitySlug'] ?? '');
        $community = $this->communityRepo->findBySlug($communitySlug);
        return Inertia::render('Management/Reports', [
            'community'   => $community !== null ? $this->serializeCommunity($community) : null,
            'reportTypes' => ['governance_summary', 'language_report', 'land_brief'],
            'bootError' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function users(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): InertiaResponse
    {
        if ($this->communityRepo === null) {
            return Inertia::render('Management/Users', [
                'community' => null,
                'bootError' => 'User services are not configured yet.',
            ]);
        }

        $communitySlug = (string) ($params['communitySlug'] ?? '');
        $community = $this->communityRepo->findBySlug($communitySlug);
        return Inertia::render('Management/Users', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function ingestion(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): InertiaResponse
    {
        if ($this->communityRepo === null) {
            return Inertia::render('Management/Ingestion', [
                'community' => null,
                'bootError' => 'Ingestion services are not configured yet.',
            ]);
        }

        $communitySlug = (string) ($params['communitySlug'] ?? '');
        $community = $this->communityRepo->findBySlug($communitySlug);
        return Inertia::render('Management/Ingestion', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function exportPage(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): InertiaResponse
    {
        if ($this->communityRepo === null) {
            return Inertia::render('Management/Export', [
                'community' => null,
                'bootError' => 'Export/import services are not configured yet.',
            ]);
        }

        $communitySlug = (string) ($params['communitySlug'] ?? '');
        $community = $this->communityRepo->findBySlug($communitySlug);
        return Inertia::render('Management/Export', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ]);
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
