<?php

declare(strict_types=1);

namespace Giiken\Query;

use Giiken\Pipeline\Provider\LlmProviderInterface;
use Waaseyaa\Access\AccountInterface;

final class QaService
{
    private const SYSTEM_PROMPT = 'You are a knowledge assistant for an Indigenous community. Answer the question using ONLY the provided context. Cite sources by their item ID in square brackets (e.g., [item-123]). If the context does not contain enough information to answer, say so. Never fabricate information.';
    private const NO_INFO_MSG   = "I don't have enough information in this community's knowledge base to answer that question.";
    private const CITATION_RE   = '/\[(item-[^\]]+)\]/';

    public function __construct(
        private readonly SearchService $searchService,
        private readonly LlmProviderInterface $llmProvider,
    ) {}

    public function ask(string $question, string $communityId, ?AccountInterface $account): QaResponse
    {
        $searchQuery = new SearchQuery(
            query: $question,
            communityId: $communityId,
            page: 1,
            pageSize: 5,
        );

        $results = $this->searchService->search($searchQuery, $account);

        if ($results->items === []) {
            return new QaResponse(
                answer: self::NO_INFO_MSG,
                citedItemIds: [],
                noRelevantItems: true,
            );
        }

        $contextLines = array_map(
            static fn (SearchResultItem $item): string =>
                "[{$item->id}] {$item->title}\n{$item->summary}",
            $results->items,
        );
        $context = implode("\n\n", $contextLines);

        $userPrompt = $context . "\n\n---\n\nQuestion: " . $question;

        $answer = $this->llmProvider->complete(self::SYSTEM_PROMPT, $userPrompt);

        $citedItemIds = $this->parseCitations($answer);

        return new QaResponse(
            answer: $answer,
            citedItemIds: $citedItemIds,
            noRelevantItems: false,
        );
    }

    /**
     * @return string[]
     */
    private function parseCitations(string $answer): array
    {
        preg_match_all(self::CITATION_RE, $answer, $matches);

        return array_values(array_unique($matches[1]));
    }
}
