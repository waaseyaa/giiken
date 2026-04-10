<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Http\Controller\ManagementController;
use Giiken\Http\Inertia\InertiaHttpResponder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Foundation\Http\Inertia\InertiaFullPageRendererInterface;

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

        $this->controller = new ManagementController(
            $this->communityRepo,
            $this->createInertiaResponder(),
        );
    }

    private function createInertiaResponder(): InertiaHttpResponder
    {
        $renderer = $this->createStub(InertiaFullPageRendererInterface::class);
        $renderer->method('render')->willReturnCallback(
            static fn (array $pageObject): string => json_encode($pageObject, JSON_THROW_ON_ERROR),
        );

        return new InertiaHttpResponder($renderer, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePage(Response $response): array
    {
        $raw = $response->getContent();
        self::assertIsString($raw);
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
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
        self::assertInstanceOf(Response::class, $response);
        $page = $this->decodePage($response);
        self::assertSame('Management/Dashboard', $page['component']);
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
        self::assertInstanceOf(Response::class, $response);
        $page = $this->decodePage($response);
        self::assertSame('Management/Reports', $page['component']);
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
        self::assertInstanceOf(Response::class, $response);
        $page = $this->decodePage($response);
        self::assertSame('Management/Export', $page['component']);
    }

    #[Test]
    public function dashboard_returns_boot_error_when_services_are_not_configured(): void
    {
        $controller = new ManagementController(null, $this->createInertiaResponder());
        $account = $this->createMock(AccountInterface::class);

        $response = $controller->dashboard(
            ['communitySlug' => 'test-community'],
            [],
            $account,
            new HttpRequest(),
        );

        self::assertInstanceOf(Response::class, $response);
        $page = $this->decodePage($response);
        self::assertArrayHasKey('community', $page['props']);
        self::assertNull($page['props']['community']);
        self::assertSame('Management services are not configured yet.', $page['props']['bootError'] ?? null);
    }

    #[Test]
    public function reports_returns_boot_error_when_services_are_not_configured(): void
    {
        $controller = new ManagementController(null, $this->createInertiaResponder());
        $account = $this->createMock(AccountInterface::class);

        $response = $controller->reports(
            ['communitySlug' => 'test-community'],
            [],
            $account,
            new HttpRequest(),
        );

        self::assertInstanceOf(Response::class, $response);
        $page = $this->decodePage($response);
        self::assertArrayHasKey('community', $page['props']);
        self::assertNull($page['props']['community']);
        self::assertSame('Report services are not configured yet.', $page['props']['bootError'] ?? null);
    }

    private function makeCommunity(): Community
    {
        return Community::make([
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
