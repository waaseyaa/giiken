<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Http\Controller\DiscoveryController;
use Giiken\Query\QaResponse;
use Giiken\Query\QaServiceInterface;
use Giiken\Query\SearchQuery;
use Giiken\Query\SearchResultItem;
use Giiken\Query\SearchResultSet;
use Giiken\Query\SearchService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Inertia\InertiaResponse;

#[CoversClass(DiscoveryController::class)]
final class DiscoveryControllerTest extends TestCase
{
    private DiscoveryController $controller;
    private SearchService $searchService;
    private QaServiceInterface $qaService;
    private CommunityRepositoryInterface $communityRepo;
    private KnowledgeItemRepositoryInterface $itemRepo;

    protected function setUp(): void
    {
        $this->searchService = $this->createMock(SearchService::class);
        $this->qaService = $this->createMock(QaServiceInterface::class);
        $this->communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $this->itemRepo = $this->createMock(KnowledgeItemRepositoryInterface::class);

        $this->controller = new DiscoveryController(
            $this->searchService,
            $this->qaService,
            $this->communityRepo,
            $this->itemRepo,
        );
    }

    #[Test]
    public function index_returns_inertia_response_with_community(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);
        $this->searchService->method('search')->willReturn(SearchResultSet::empty());

        $response = $this->controller->index('test-community', null);

        self::assertInstanceOf(InertiaResponse::class, $response);
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

        $response = $this->controller->search('test-community', 'test query', 1, null);

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function ask_returns_qa_response(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $qaResponse = new QaResponse(answer: 'The answer', citedItemIds: ['1'], noRelevantItems: false);
        $this->qaService->method('ask')->willReturn($qaResponse);
        $this->searchService->method('search')->willReturn(SearchResultSet::empty());

        $response = $this->controller->ask('test-community', 'What is this?', null);

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    #[Test]
    public function show_returns_knowledge_item(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->willReturn($community);

        $item = new KnowledgeItem([
            'id' => '1',
            'community_id' => 'comm-1',
            'title' => 'Test Item',
            'content' => 'Content here',
            'knowledge_type' => 'cultural',
            'access_tier' => 'public',
        ]);
        $this->itemRepo->method('find')->willReturn($item);

        $response = $this->controller->show('test-community', '1', null);

        self::assertInstanceOf(InertiaResponse::class, $response);
    }

    private function makeCommunity(): Community
    {
        return new Community([
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
