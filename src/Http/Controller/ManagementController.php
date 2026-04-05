<?php

declare(strict_types=1);

namespace Giiken\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Export\ExportServiceInterface;
use Giiken\Export\ImportServiceInterface;
use Giiken\Query\Report\ReportServiceInterface;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;

final class ManagementController
{
    public function __construct(
        private readonly CommunityRepositoryInterface $communityRepo,
        private readonly ReportServiceInterface $reportService,
        private readonly ExportServiceInterface $exportService,
        private readonly ImportServiceInterface $importService,
    ) {}

    public function dashboard(string $communitySlug): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);
        return Inertia::render('Management/Dashboard', [
            'community' => $this->serializeCommunity($community),
        ]);
    }

    public function reports(string $communitySlug): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);
        return Inertia::render('Management/Reports', [
            'community'   => $this->serializeCommunity($community),
            'reportTypes' => ['governance_summary', 'language_report', 'land_brief'],
        ]);
    }

    public function users(string $communitySlug): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);
        return Inertia::render('Management/Users', [
            'community' => $this->serializeCommunity($community),
        ]);
    }

    public function ingestion(string $communitySlug): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);
        return Inertia::render('Management/Ingestion', [
            'community' => $this->serializeCommunity($community),
        ]);
    }

    public function exportPage(string $communitySlug): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);
        return Inertia::render('Management/Export', [
            'community' => $this->serializeCommunity($community),
        ]);
    }

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
