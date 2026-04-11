<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Http\Controller\DiscoveryController;
use Giiken\Http\Inertia\InertiaHttpResponder;
use Giiken\Query\QaResponse;
use Giiken\Query\QaServiceInterface;
use Giiken\Query\SearchQuery;
use Giiken\Query\SearchResultItem;
use Giiken\Query\SearchResultSet;
use Giiken\Query\SearchService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Foundation\Http\Inertia\InertiaFullPageRendererInterface;

#[CoversClass(DiscoveryController::class)]
final class DiscoveryControllerTest extends TestCase
{
    private DiscoveryController $controller;
    /** @var SearchService&MockObject */
    private SearchService $searchService;
    /** @var QaServiceInterface&MockObject */
    private QaServiceInterface $qaService;
    /** @var CommunityRepositoryInterface&MockObject */
    private CommunityRepositoryInterface $communityRepo;
    /** @var KnowledgeItemRepositoryInterface&MockObject */
    private KnowledgeItemRepositoryInterface $itemRepo;
    private AccountInterface $account;

    protected function setUp(): void
    {
        $this->searchService = $this->createMock(SearchService::class);
        $this->qaService = $this->createMock(QaServiceInterface::class);
        $this->communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $this->itemRepo = $this->createMock(KnowledgeItemRepositoryInterface::class);
        $this->account = $this->createMock(AccountInterface::class);

        $this->controller = new DiscoveryController(
            $this->searchService,
            $this->qaService,
            $this->communityRepo,
            $this->itemRepo,
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
    public function index_returns_inertia_response_with_community(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);
        $this->searchService->method('search')->willReturn(SearchResultSet::empty());

        $response = $this->controller->index(
            ['communitySlug' => 'test-community'],
            [],
            $this->account,
            new HttpRequest(),
        );

        self::assertInstanceOf(Response::class, $response);
        $page = $this->decodePage($response);
        self::assertSame('Discovery/Index', $page['component']);
        self::assertIsArray($page['props']['community'] ?? null);
    }

    #[Test]
    public function search_returns_results(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $resultSet = new SearchResultSet(
            items: [new SearchResultItem('1', 'Title', 'Summary', KnowledgeType::Cultural, 0.9)],
            totalHits: 1,
            totalPages: 1,
        );
        $this->searchService->method('search')->willReturn($resultSet);

        $response = $this->controller->search(
            ['communitySlug' => 'test-community'],
            ['q' => 'test query', 'page' => 1],
            $this->account,
            new HttpRequest(),
        );

        self::assertInstanceOf(Response::class, $response);
        $page = $this->decodePage($response);
        self::assertSame('Discovery/Search', $page['component']);
        self::assertSame(1, $page['props']['results']['totalHits'] ?? null);
    }

    #[Test]
    public function search_passes_q_query_string_through_to_search_service(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $capturedQuery = null;
        $this->searchService
            ->expects(self::once())
            ->method('search')
            ->willReturnCallback(
                function (SearchQuery $q) use (&$capturedQuery): SearchResultSet {
                    $capturedQuery = $q;

                    return new SearchResultSet(items: [], totalHits: 0, totalPages: 0);
                },
            );

        $request = HttpRequest::create('/test-community/search', 'GET', [
            'q' => 'governance',
            'page' => '2',
        ]);

        $response = $this->controller->search(
            ['communitySlug' => 'test-community'],
            ['q' => 'governance', 'page' => '2'],
            $this->account,
            $request,
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertInstanceOf(SearchQuery::class, $capturedQuery);
        self::assertSame('governance', $capturedQuery->query);
        self::assertSame('comm-1', $capturedQuery->communityId);
        self::assertSame(2, $capturedQuery->page);

        $page = $this->decodePage($response);
        self::assertSame('governance', $page['props']['query'] ?? null);
        self::assertSame(2, $page['props']['page'] ?? null);
    }

    #[Test]
    public function search_defaults_to_empty_query_when_q_missing(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $capturedQuery = null;
        $this->searchService
            ->expects(self::once())
            ->method('search')
            ->willReturnCallback(
                function (SearchQuery $q) use (&$capturedQuery): SearchResultSet {
                    $capturedQuery = $q;

                    return new SearchResultSet(items: [], totalHits: 0, totalPages: 0);
                },
            );

        $this->controller->search(
            ['communitySlug' => 'test-community'],
            [],
            $this->account,
            new HttpRequest(),
        );

        self::assertInstanceOf(SearchQuery::class, $capturedQuery);
        self::assertSame('', $capturedQuery->query);
        self::assertSame(1, $capturedQuery->page);
    }

    #[Test]
    public function ask_returns_qa_response(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $qaResponse = new QaResponse(answer: 'The answer', citedItemIds: ['1'], noRelevantItems: false);
        $this->qaService->method('ask')->willReturn($qaResponse);
        $this->searchService->method('search')->willReturn(SearchResultSet::empty());

        $response = $this->controller->ask(
            ['communitySlug' => 'test-community'],
            ['question' => 'What is this?'],
            $this->account,
            new HttpRequest(),
        );

        self::assertInstanceOf(Response::class, $response);
        $page = $this->decodePage($response);
        self::assertSame('Discovery/Ask', $page['component']);
        self::assertSame('The answer', $page['props']['answer'] ?? null);
        self::assertSame([], $page['props']['citations'] ?? null);
    }

    #[Test]
    public function show_returns_knowledge_item(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $item = KnowledgeItem::make([
            'id' => '1',
            'community_id' => 'comm-1',
            'title' => 'Test Item',
            'content' => 'Content here',
            'knowledge_type' => 'cultural',
            'access_tier' => 'public',
        ]);
        $this->itemRepo->method('find')->willReturn($item);

        $response = $this->controller->show(
            ['communitySlug' => 'test-community', 'itemId' => '1'],
            [],
            $this->account,
            new HttpRequest(),
        );

        self::assertInstanceOf(Response::class, $response);
        $page = $this->decodePage($response);
        self::assertSame('Discovery/Show', $page['component']);
        self::assertSame('Test Item', $page['props']['item']['title'] ?? null);
    }

    private function makeCommunity(): Community
    {
        return Community::make([
            'id' => 'comm-1',
            'name' => 'Test Community',
            'slug' => 'test-community',
            'sovereignty_profile' => 'local',
            'locale' => 'en',
            'contact_email' => 'test@example.com',
            'wiki_schema' => [],
        ]);
    }
}
