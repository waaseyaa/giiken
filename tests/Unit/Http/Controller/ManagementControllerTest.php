<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Http\Controller\ManagementController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Inertia\InertiaResponse;

#[CoversClass(ManagementController::class)]
final class ManagementControllerTest extends TestCase
{
    private ManagementController $controller;
    /** @var CommunityRepositoryInterface&MockObject */
    private CommunityRepositoryInterface $communityRepo;
    private AccountInterface $account;

    protected function setUp(): void
    {
        $this->communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $this->account = $this->createMock(AccountInterface::class);

        $this->controller = new ManagementController($this->communityRepo);
    }

    #[Test]
    public function dashboard_returns_inertia_response(): void
    {
        $this->communityRepo->method('findBySlug')->willReturn($this->makeCommunity());
        $response = $this->controller->dashboard(
            ['communitySlug' => 'test-community'],
            [],
            $this->account,
            new HttpRequest(),
        );
        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function reports_returns_inertia_response(): void
    {
        $this->communityRepo->method('findBySlug')->willReturn($this->makeCommunity());
        $response = $this->controller->reports(
            ['communitySlug' => 'test-community'],
            [],
            $this->account,
            new HttpRequest(),
        );
        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function export_page_returns_inertia_response(): void
    {
        $this->communityRepo->method('findBySlug')->willReturn($this->makeCommunity());
        $response = $this->controller->exportPage(
            ['communitySlug' => 'test-community'],
            [],
            $this->account,
            new HttpRequest(),
        );
        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function dashboard_returns_boot_error_when_services_are_not_configured(): void
    {
        $controller = new ManagementController();
        $account = $this->createMock(AccountInterface::class);

        $response = $controller->dashboard(
            ['communitySlug' => 'test-community'],
            [],
            $account,
            new HttpRequest(),
        );

        self::assertInstanceOf(InertiaResponse::class, $response);
        self::assertArrayHasKey('community', $response->props);
        self::assertNull($response->props['community']);
        self::assertSame('Management services are not configured yet.', $response->props['bootError'] ?? null);
    }

    #[Test]
    public function reports_returns_boot_error_when_services_are_not_configured(): void
    {
        $controller = new ManagementController();
        $account = $this->createMock(AccountInterface::class);

        $response = $controller->reports(
            ['communitySlug' => 'test-community'],
            [],
            $account,
            new HttpRequest(),
        );

        self::assertInstanceOf(InertiaResponse::class, $response);
        self::assertArrayHasKey('community', $response->props);
        self::assertNull($response->props['community']);
        self::assertSame('Report services are not configured yet.', $response->props['bootError'] ?? null);
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
