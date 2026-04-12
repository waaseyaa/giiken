<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Entity\Community\CommunityRepositoryInterface;
use App\Query\QaServiceInterface;
use App\Query\Report\ReportRequest;
use App\Query\Report\ReportServiceInterface;
use App\Query\SynthesisService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;

/**
 * JSON endpoints for Phase 3 query layer (Q&A, reports, synthesis).
 */
final class QueryApiController
{
    public function __construct(
        private readonly ?CommunityRepositoryInterface $communityRepo = null,
        private readonly ?QaServiceInterface $qaService = null,
        private readonly ?ReportServiceInterface $reportService = null,
        private readonly ?SynthesisService $synthesisService = null,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function ask(
        array $params,
        array $query,
        AccountInterface $account,
        HttpRequest $httpRequest,
    ): Response {
        if ($this->communityRepo === null || $this->qaService === null) {
            return new JsonResponse([
                'error' => 'Q&A API is not configured.',
            ], 503, ['Content-Type' => 'application/json']);
        }

        $body = $this->jsonBody($httpRequest);
        if ($body === null) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
        }

        $communitySlug = (string) ($body['communitySlug'] ?? '');
        $question      = trim((string) ($body['question'] ?? ''));
        if ($communitySlug === '' || $question === '') {
            return new JsonResponse(['error' => 'communitySlug and question are required.'], 422);
        }

        $community = $this->communityRepo->findBySlug($communitySlug);
        if ($community === null) {
            return new JsonResponse(['error' => 'Community not found.'], 404);
        }

        $communityId = (string) $community->get('id');
        $qa          = $this->qaService->ask($question, $communityId, $account);

        return new JsonResponse([
            'answer'            => $qa->answer,
            'citedItemIds'      => $qa->citedItemIds,
            'citations'         => array_map(static fn ($c): array => [
                'itemId'  => $c->itemId,
                'title'   => $c->title,
                'excerpt' => $c->excerpt,
            ], $qa->citations),
            'noRelevantItems'   => $qa->noRelevantItems,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function report(
        array $params,
        array $query,
        AccountInterface $account,
        HttpRequest $httpRequest,
    ): Response {
        if ($this->communityRepo === null || $this->reportService === null) {
            return new JsonResponse(['error' => 'Report API is not configured.'], 503);
        }

        $body = $this->jsonBody($httpRequest);
        if ($body === null) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
        }

        $communitySlug = (string) ($body['communitySlug'] ?? '');
        if ($communitySlug === '') {
            return new JsonResponse(['error' => 'communitySlug is required.'], 422);
        }

        $community = $this->communityRepo->findBySlug($communitySlug);
        if ($community === null) {
            return new JsonResponse(['error' => 'Community not found.'], 404);
        }

        $reportType = (string) ($body['reportType'] ?? '');
        $from       = (string) ($body['dateFrom'] ?? $body['from'] ?? '');
        $to         = (string) ($body['dateTo'] ?? $body['to'] ?? '');
        if ($reportType === '' || $from === '' || $to === '') {
            return new JsonResponse(['error' => 'reportType, dateFrom, and dateTo are required.'], 422);
        }

        /** @var string[] $kt */
        $kt = [];
        if (isset($body['knowledgeTypes']) && is_array($body['knowledgeTypes'])) {
            $kt = array_values(array_map('strval', $body['knowledgeTypes']));
        }

        try {
            $result = $this->reportService->generateFromRequest(
                $community,
                new ReportRequest(
                    reportType: $reportType,
                    dateFromIso: $from,
                    dateToIso: $to,
                    knowledgeTypeValues: $kt,
                ),
                $account,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse([
            'markdown'          => $result->markdown,
            'includedItemCount' => $result->includedItemCount,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function saveSynthesis(
        array $params,
        array $query,
        AccountInterface $account,
        HttpRequest $httpRequest,
    ): Response {
        if ($this->communityRepo === null || $this->synthesisService === null) {
            return new JsonResponse(['error' => 'Synthesis API is not configured.'], 503);
        }

        if (!$account->isAuthenticated()) {
            return new JsonResponse(['error' => 'Authentication required.'], 401);
        }

        $body = $this->jsonBody($httpRequest);
        if ($body === null) {
            return new JsonResponse(['error' => 'Invalid JSON body.'], 400);
        }

        $communitySlug = (string) ($body['communitySlug'] ?? '');
        if ($communitySlug === '') {
            return new JsonResponse(['error' => 'communitySlug is required.'], 422);
        }

        $community = $this->communityRepo->findBySlug($communitySlug);
        if ($community === null) {
            return new JsonResponse(['error' => 'Community not found.'], 404);
        }

        $title = (string) ($body['title'] ?? 'Q&A synthesis');
        $text  = (string) ($body['content'] ?? $body['answer'] ?? '');
        /** @var mixed $rawIds */
        $rawIds = $body['citedItemIds'] ?? [];
        if (!is_array($rawIds)) {
            return new JsonResponse(['error' => 'citedItemIds must be an array of strings.'], 422);
        }
        $citedIds = array_values(array_map('strval', $rawIds));

        try {
            $saved = $this->synthesisService->saveFromQa(
                (string) $community->get('id'),
                $title,
                $text,
                $citedIds,
                $account,
            );
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 403);
        }

        return new JsonResponse(['item' => $saved], 201);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function jsonBody(HttpRequest $httpRequest): ?array
    {
        $raw = $httpRequest->getContent();
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
