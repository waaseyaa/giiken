<?php

declare(strict_types=1);

namespace Giiken\Http\Controller;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Query\QaServiceInterface;
use Giiken\Query\SearchQuery;
use Giiken\Query\SearchResultSet;
use Giiken\Query\SearchService;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Foundation\Http\Inbound\InboundHttpRequest;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;

final class DiscoveryController
{
    public function __construct(
        private readonly ?SearchService $searchService = null,
        private readonly ?QaServiceInterface $qaService = null,
        private readonly ?CommunityRepositoryInterface $communityRepo = null,
        private readonly ?KnowledgeItemRepositoryInterface $itemRepo = null,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function index(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): InertiaResponse
    {
        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');

        if ($this->searchService === null || $this->communityRepo === null) {
            return Inertia::render('Discovery/Index', [
                'community' => null,
                'recentItems' => $this->emptyResultSet(),
                'bootError' => 'Discovery services are not configured yet.',
            ]);
        }

        $searchService = $this->searchService;
        $communityRepo = $this->communityRepo;

        $community = $communityRepo->findBySlug($communitySlug);
        if ($community === null) {
            return Inertia::render('Discovery/Index', [
                'community' => null,
                'recentItems' => $this->emptyResultSet(),
            ]);
        }

        $recent = $searchService->search(
            new SearchQuery(query: '', communityId: (string) $community->get('id')),
            $account,
        );

        return Inertia::render('Discovery/Index', [
            'community' => $this->serializeCommunity($community),
            'recentItems' => $this->serializeResultSet($recent),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function search(
        array $params,
        array $query,
        AccountInterface $account,
        HttpRequest $httpRequest,
    ): InertiaResponse {
        if ($this->searchService === null || $this->communityRepo === null) {
            $searchQuery = (string) ($query['query'] ?? '');
            $page = max(1, (int) ($query['page'] ?? 1));

            return Inertia::render('Discovery/Search', [
                'community' => null,
                'query' => $searchQuery,
                'results' => $this->emptyResultSet(),
                'page' => $page,
                'bootError' => 'Search services are not configured yet.',
            ]);
        }

        $searchService = $this->searchService;
        $communityRepo = $this->communityRepo;

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $searchQuery = (string) $inbound->queryParam('query', '');
        $page = max(1, (int) $inbound->queryParam('page', 1));

        $community = $communityRepo->findBySlug($communitySlug);
        if ($community === null) {
            return Inertia::render('Discovery/Search', [
                'community' => null,
                'query' => $searchQuery,
                'results' => $this->emptyResultSet(),
                'page' => $page,
            ]);
        }

        $results = $searchService->search(
            new SearchQuery(
                query: $searchQuery,
                communityId: (string) $community->get('id'),
                page: $page,
            ),
            $account,
        );

        return Inertia::render('Discovery/Search', [
            'community' => $this->serializeCommunity($community),
            'query' => $searchQuery,
            'results' => $this->serializeResultSet($results),
            'page' => $page,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function ask(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): InertiaResponse
    {
        if ($this->searchService === null || $this->qaService === null || $this->communityRepo === null) {
            $question = (string) ($query['question'] ?? '');

            return Inertia::render('Discovery/Ask', [
                'community' => null,
                'question' => $question,
                'answer' => '',
                'citedItemIds' => [],
                'noRelevantItems' => true,
                'relatedItems' => $this->emptyResultSet(),
                'bootError' => 'Q&A services are not configured yet.',
            ]);
        }

        $searchService = $this->searchService;
        $qaService = $this->qaService;
        $communityRepo = $this->communityRepo;

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $question = (string) $inbound->queryParam('question', '');

        $community = $communityRepo->findBySlug($communitySlug);
        if ($community === null) {
            return Inertia::render('Discovery/Ask', [
                'community' => null,
                'question' => $question,
                'answer' => '',
                'citedItemIds' => [],
                'noRelevantItems' => true,
                'relatedItems' => $this->emptyResultSet(),
            ]);
        }
        $communityId = (string) $community->get('id');

        $qaResponse = $qaService->ask($question, $communityId, $account);

        $related = $searchService->search(
            new SearchQuery(query: $question, communityId: $communityId, pageSize: 5),
            $account,
        );

        return Inertia::render('Discovery/Ask', [
            'community' => $this->serializeCommunity($community),
            'question' => $question,
            'answer' => $qaResponse->answer,
            'citedItemIds' => $qaResponse->citedItemIds,
            'noRelevantItems' => $qaResponse->noRelevantItems,
            'relatedItems' => $this->serializeResultSet($related),
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function show(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): InertiaResponse
    {
        if ($this->communityRepo === null || $this->itemRepo === null) {
            return Inertia::render('Discovery/Show', [
                'community' => null,
                'item' => null,
                'bootError' => 'Knowledge item services are not configured yet.',
            ]);
        }

        $communityRepo = $this->communityRepo;
        $itemRepo = $this->itemRepo;

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $itemId = (string) $inbound->routeParam('itemId', '');

        $community = $communityRepo->findBySlug($communitySlug);
        $item = $itemRepo->find($itemId);
        if ($community === null || $item === null) {
            return Inertia::render('Discovery/Show', [
                'community' => $community !== null ? $this->serializeCommunity($community) : null,
                'item' => null,
            ]);
        }

        return Inertia::render('Discovery/Show', [
            'community' => $this->serializeCommunity($community),
            'item' => [
                'id' => $item->get('id'),
                'title' => $item->getTitle(),
                'content' => $item->getContent(),
                'knowledgeType' => $item->getKnowledgeType()?->value,
                'accessTier' => $item->getAccessTier()->value,
                'compiledAt' => $item->getCompiledAt(),
                'createdAt' => $item->getCreatedAt(),
                'updatedAt' => $item->getUpdatedAt(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCommunity(Community $community): array
    {
        return [
            'id' => $community->get('id'),
            'name' => $community->getName(),
            'slug' => $community->getSlug(),
            'locale' => $community->getLocale(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeResultSet(SearchResultSet $resultSet): array
    {
        return [
            'items' => array_map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'summary' => $item->summary,
                'knowledgeType' => $item->knowledgeType?->value,
                'score' => $item->score,
            ], $resultSet->items),
            'totalHits' => $resultSet->totalHits,
            'totalPages' => $resultSet->totalPages,
        ];
    }

    /**
     * @return array{items: array<array<string, mixed>>, totalHits: int, totalPages: int}
     */
    private function emptyResultSet(): array
    {
        return [
            'items' => [],
            'totalHits' => 0,
            'totalPages' => 0,
        ];
    }
}
