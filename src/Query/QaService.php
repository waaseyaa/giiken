<?php

declare(strict_types=1);

namespace Giiken\Query;

use Giiken\Pipeline\Provider\LlmProviderInterface;
use Waaseyaa\Access\AccountInterface;

final class QaService implements QaServiceInterface
{
    private const SYSTEM_PROMPT = 'You are a knowledge assistant for an Indigenous community. Answer the question using ONLY the provided context. Cite sources by repeating each source ID in square brackets exactly as shown at the start of every context block (e.g. [42] when the line begins with [42]). If the context does not contain enough information to answer, say so. Never fabricate information.';
    private const NO_INFO_MSG   = "I don't have enough information in this community's knowledge base to answer that question.";
    /** IDs from context lines: alphanumeric, underscore, hyphen (matches numeric DB ids). */
    private const CITATION_RE = '/\[([a-zA-Z0-9_-]+)\]/';

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
                citations: [],
            );
        }

        /** @var array<string, SearchResultItem> $byId */
        $byId = [];
        foreach ($results->items as $item) {
            $byId[$item->id] = $item;
        }

        $contextLines = array_map(
            static fn (SearchResultItem $item): string =>
                "[{$item->id}] {$item->title}\n{$item->summary}",
            $results->items,
        );
        $context = implode("\n\n", $contextLines);

        $userPrompt = $context . "\n\n---\n\nQuestion: " . $question;

        $answer = $this->llmProvider->complete(self::SYSTEM_PROMPT, $userPrompt);

        $citedItemIds = $this->parseCitations($answer, $byId);
        $citations    = $this->buildCitations($citedItemIds, $byId);

        return new QaResponse(
            answer: $answer,
            citedItemIds: $citedItemIds,
            noRelevantItems: false,
            citations: $citations,
        );
    }

    /**
     * @param array<string, SearchResultItem> $allowedIds
     *
     * @return string[]
     */
    private function parseCitations(string $answer, array $allowedIds): array
    {
        preg_match_all(self::CITATION_RE, $answer, $matches);
        $raw = array_values(array_unique($matches[1]));

        return array_values(array_filter($raw, static fn (string $id): bool => isset($allowedIds[$id])));
    }

    /**
     * @param string[]                        $citedItemIds
     * @param array<string, SearchResultItem> $byId
     *
     * @return QaCitation[]
     */
    private function buildCitations(array $citedItemIds, array $byId): array
    {
        $out = [];
        foreach ($citedItemIds as $id) {
            $item = $byId[$id] ?? null;
            if ($item === null) {
                continue;
            }
            $out[] = new QaCitation(
                itemId: $item->id,
                title: $item->title,
                excerpt: $this->excerpt($item->summary),
                knowledgeType: $item->knowledgeType?->value,
            );
        }

        return $out;
    }

    private function excerpt(string $text, int $maxLen = 280): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if (mb_strlen($text, 'UTF-8') <= $maxLen) {
            return $text;
        }

        return mb_substr($text, 0, $maxLen - 1, 'UTF-8') . '…';
    }
}
