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
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\InertiaResponse;

final class DiscoveryController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly QaServiceInterface $qaService,
        private readonly CommunityRepositoryInterface $communityRepo,
        private readonly KnowledgeItemRepositoryInterface $itemRepo,
    ) {}

    public function index(string $communitySlug, ?AccountInterface $account): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);

        $recent = $this->searchService->search(
            new SearchQuery(query: '', communityId: (string) $community->get('id')),
            $account,
        );

        return Inertia::render('Discovery/Index', [
            'community' => $this->serializeCommunity($community),
            'recentItems' => $this->serializeResultSet($recent),
        ]);
    }

    public function search(
        string $communitySlug,
        string $query,
        int $page,
        ?AccountInterface $account,
    ): InertiaResponse {
        $community = $this->communityRepo->findBySlug($communitySlug);

        $results = $this->searchService->search(
            new SearchQuery(
                query: $query,
                communityId: (string) $community->get('id'),
                page: $page,
            ),
            $account,
        );

        return Inertia::render('Discovery/Search', [
            'community' => $this->serializeCommunity($community),
            'query' => $query,
            'results' => $this->serializeResultSet($results),
            'page' => $page,
        ]);
    }

    public function ask(string $communitySlug, string $question, ?AccountInterface $account): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);
        $communityId = (string) $community->get('id');

        $qaResponse = $this->qaService->ask($question, $communityId, $account);

        $related = $this->searchService->search(
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

    public function show(string $communitySlug, string $itemId, ?AccountInterface $account): InertiaResponse
    {
        $community = $this->communityRepo->findBySlug($communitySlug);
        $item = $this->itemRepo->find($itemId);

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
}
