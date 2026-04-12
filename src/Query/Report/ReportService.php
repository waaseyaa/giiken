<?php

declare(strict_types=1);

namespace App\Query\Report;

use App\Access\CommunityRole;
use App\Access\KnowledgeItemAccessPolicy;
use App\Entity\Community\Community;
use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Entity\KnowledgeItem\KnowledgeType;
use Waaseyaa\Access\AccountInterface;

final class ReportService implements ReportServiceInterface
{
    /** @var array<string, ReportRendererInterface> */
    private array $renderers;

    /**
     * Access requirements per report type (minimum CommunityRole rank required).
     *
     * @var array<string, CommunityRole>
     */
    private const REQUIRED_ROLES = [
        'governance_summary' => CommunityRole::Staff,
        'language_report'    => CommunityRole::Member,
        'land_brief'         => CommunityRole::KnowledgeKeeper,
    ];

    /**
     * Knowledge type filter per report type.
     *
     * @var array<string, KnowledgeType>
     */
    private const TYPE_FILTERS = [
        'governance_summary' => KnowledgeType::Governance,
        'language_report'    => KnowledgeType::Cultural,
        'land_brief'         => KnowledgeType::Land,
    ];

    /** @param ReportRendererInterface[] $renderers */
    public function __construct(
        array $renderers,
        private readonly KnowledgeItemRepositoryInterface $repository,
        private readonly KnowledgeItemAccessPolicy $accessPolicy,
    ) {
        foreach ($renderers as $renderer) {
            $this->renderers[$renderer->getType()] = $renderer;
        }
    }

    public function generate(
        string $reportType,
        Community $community,
        DateRange $dateRange,
        AccountInterface $account,
    ): string {
        $result = $this->generateFromRequest(
            $community,
            new ReportRequest(
                reportType: $reportType,
                dateFromIso: $dateRange->from->format('Y-m-d'),
                dateToIso: $dateRange->to->format('Y-m-d'),
            ),
            $account,
        );

        return $result->markdown;
    }

    public function generateFromRequest(
        Community $community,
        ReportRequest $request,
        AccountInterface $account,
    ): ReportResult {
        $reportType = $request->reportType;

        if (!isset($this->renderers[$reportType])) {
            throw new \InvalidArgumentException("Unknown report type: {$reportType}");
        }

        $renderer = $this->renderers[$reportType];

        $requiredRole = self::REQUIRED_ROLES[$reportType] ?? CommunityRole::Admin;
        $accountRole  = $this->resolveRole($account, (string) $community->get('id'));

        if ($accountRole->rank() < $requiredRole->rank()) {
            throw new \RuntimeException(
                "Access denied: {$reportType} requires {$requiredRole->value} or above",
            );
        }

        try {
            $from = new \DateTimeImmutable($request->dateFromIso);
            $to   = new \DateTimeImmutable($request->dateToIso);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException('Invalid date range: ' . $e->getMessage(), 0, $e);
        }

        $dateRange = new DateRange(from: $from, to: $to);

        $communityId = (string) $community->get('id');
        $allItems    = $this->repository->findByCommunity($communityId);

        $allItems = array_values(array_filter(
            $allItems,
            fn (KnowledgeItem $item): bool => $this->accessPolicy->access($item, 'view', $account)->isAllowed(),
        ));

        $typeFilter = self::TYPE_FILTERS[$reportType] ?? null;
        if ($request->knowledgeTypeValues !== []) {
            $allowedTypes = [];
            foreach ($request->knowledgeTypeValues as $raw) {
                $kt = KnowledgeType::tryFrom((string) $raw);
                if ($kt !== null) {
                    $allowedTypes[$kt->value] = $kt;
                }
            }
            if ($allowedTypes === []) {
                throw new \InvalidArgumentException('knowledgeTypes contained no valid knowledge type values.');
            }
            $allItems = array_values(array_filter(
                $allItems,
                static fn (KnowledgeItem $item): bool =>
                    $item->getKnowledgeType() !== null
                    && isset($allowedTypes[$item->getKnowledgeType()->value]),
            ));
        } elseif ($typeFilter !== null) {
            $allItems = array_values(array_filter(
                $allItems,
                static fn (KnowledgeItem $item): bool => $item->getKnowledgeType() === $typeFilter,
            ));
        }

        $filtered = array_values(array_filter(
            $allItems,
            fn (KnowledgeItem $item): bool => $this->itemTimestampInRange($item, $dateRange),
        ));

        $markdown = $renderer->render($community, $filtered, $dateRange);

        return new ReportResult(markdown: $markdown, includedItemCount: count($filtered));
    }

    private function itemTimestampInRange(KnowledgeItem $item, DateRange $dateRange): bool
    {
        $ts = $this->itemReportTimestamp($item);
        if ($ts === null) {
            return false;
        }

        return $dateRange->contains($ts);
    }

    private function itemReportTimestamp(KnowledgeItem $item): ?\DateTimeImmutable
    {
        $compiled = $item->getCompiledAt();
        if ($compiled !== '') {
            try {
                return new \DateTimeImmutable($compiled);
            } catch (\Exception) {
                // fall through to created_at
            }
        }

        $createdAt = $item->getCreatedAt();
        if ($createdAt === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($createdAt);
        } catch (\Exception) {
            return null;
        }
    }

    private function resolveRole(AccountInterface $account, string $communityId): CommunityRole
    {
        $prefix = "giiken.community.{$communityId}.";

        foreach ($account->getRoles() as $role) {
            if (str_starts_with($role, $prefix)) {
                $slug        = substr($role, strlen($prefix));
                $communityRole = CommunityRole::tryFrom($slug);

                if ($communityRole !== null) {
                    return $communityRole;
                }
            }
        }

        return CommunityRole::Public;
    }
}
