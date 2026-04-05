<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Export\ExportServiceInterface;
use Giiken\Export\ImportServiceInterface;
use Giiken\Http\Controller\ManagementController;
use Giiken\Query\Report\ReportServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Inertia\InertiaResponse;

#[CoversClass(ManagementController::class)]
final class ManagementControllerTest extends TestCase
{
    private ManagementController $controller;
    private CommunityRepositoryInterface $communityRepo;
    private ReportServiceInterface $reportService;
    private ExportServiceInterface $exportService;
    private ImportServiceInterface $importService;

    protected function setUp(): void
    {
        $this->communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $this->reportService = $this->createMock(ReportServiceInterface::class);
        $this->exportService = $this->createMock(ExportServiceInterface::class);
        $this->importService = $this->createMock(ImportServiceInterface::class);

        $this->controller = new ManagementController(
            $this->communityRepo,
            $this->reportService,
            $this->exportService,
            $this->importService,
        );
    }

    #[Test]
    public function dashboard_returns_inertia_response(): void
    {
        $this->communityRepo->method('findBySlug')->willReturn($this->makeCommunity());
        $response = $this->controller->dashboard('test-community');
        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function reports_returns_inertia_response(): void
    {
        $this->communityRepo->method('findBySlug')->willReturn($this->makeCommunity());
        $response = $this->controller->reports('test-community');
        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function export_page_returns_inertia_response(): void
    {
        $this->communityRepo->method('findBySlug')->willReturn($this->makeCommunity());
        $response = $this->controller->exportPage('test-community');
        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    private function makeCommunity(): Community
    {
        return new Community([
            'id'                  => 'comm-1',
            'name'                => 'Test Community',
            'slug'                => 'test-community',
            'sovereignty_profile' => 'local',
            'locale'              => 'en',
            'contact_email'       => 'test@example.com',
            'wiki_schema'         => [],
        ]);
    }
}
