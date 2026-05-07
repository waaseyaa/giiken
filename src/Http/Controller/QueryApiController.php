<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Entity\Community\CommunityRepositoryInterface;
use App\Http\Api\Ask\AskRequestValidator;
use App\Http\RateLimit\RequestRateLimiterInterface;
use App\Query\QaServiceInterface;
use App\Query\Report\ReportRequest;
use App\Query\Report\ReportServiceInterface;
use App\Query\SynthesisService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

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
        private readonly ?AskRequestValidator $askRequestValidator = null,
        private readonly ?RequestRateLimiterInterface $askRateLimiter = null,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function ask(
        #[MapRoute] array $params,
        #[MapQuery] array $query,
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

        try {
            $requestData = ($this->askRequestValidator ?? new AskRequestValidator(2_000))->validate($body);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }

        $community = $this->communityRepo->findBySlug($requestData->communitySlug);
        if ($community === null) {
            return new JsonResponse(['error' => 'Community not found.'], 404);
        }

        $remoteAddress = (string) ($httpRequest->server->get('REMOTE_ADDR') ?? 'unknown');
        $rateLimitKey = 'ask:' . $requestData->communitySlug . ':' . $remoteAddress;
        $rateLimit = ($this->askRateLimiter ?? null)?->consume($rateLimitKey);
        if ($rateLimit !== null && !$rateLimit->allowed) {
            return new JsonResponse([
                'error' => 'Rate limit exceeded. Retry later.',
                'retryAfterSeconds' => $rateLimit->retryAfterSeconds,
            ], 429, [
                'Retry-After' => (string) $rateLimit->retryAfterSeconds,
                'X-RateLimit-Limit' => (string) $rateLimit->limit,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        $communityId = (string) $community->get('id');
        $qa          = $this->qaService->ask($requestData->question, $communityId, $account);

        return new JsonResponse([
            'answer'            => $qa->answer,
            'citedItemIds'      => $qa->citedItemIds,
            'citations'         => array_map(static fn ($c): array => [
                'itemId'  => $c->itemId,
                'title'   => $c->title,
                'excerpt' => $c->excerpt,
            ], $qa->citations),
            'noRelevantItems'   => $qa->noRelevantItems,
        ], 200, $rateLimit === null ? [] : [
            'X-RateLimit-Limit' => (string) $rateLimit->limit,
            'X-RateLimit-Remaining' => (string) $rateLimit->remaining,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function report(
        #[MapRoute] array $params,
        #[MapQuery] array $query,
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
        #[MapRoute] array $params,
        #[MapQuery] array $query,
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
