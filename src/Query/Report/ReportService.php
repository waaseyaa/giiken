<?php

declare(strict_types=1);

namespace Giiken\Query\Report;

use Giiken\Access\CommunityRole;
use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Waaseyaa\Access\AccountInterface;

final class ReportService
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
    public function __construct(array $renderers, private readonly KnowledgeItemRepositoryInterface $repository)
    {
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
        // 1. Resolve renderer
        if (!isset($this->renderers[$reportType])) {
            throw new \InvalidArgumentException("Unknown report type: {$reportType}");
        }

        $renderer = $this->renderers[$reportType];

        // 2. Check access
        $requiredRole = self::REQUIRED_ROLES[$reportType] ?? CommunityRole::Admin;
        $accountRole  = $this->resolveRole($account, (string) $community->get('id'));

        if ($accountRole->rank() < $requiredRole->rank()) {
            throw new \RuntimeException(
                "Access denied: {$reportType} requires {$requiredRole->value} or above",
            );
        }

        // 3. Load items
        $communityId = (string) $community->get('id');
        $allItems    = $this->repository->findByCommunity($communityId);

        // 4. Filter by knowledge type
        $typeFilter = self::TYPE_FILTERS[$reportType] ?? null;
        if ($typeFilter !== null) {
            $allItems = array_values(array_filter(
                $allItems,
                static fn ($item) => $item->getKnowledgeType() === $typeFilter,
            ));
        }

        // 5. Filter by date range
        $filtered = array_values(array_filter(
            $allItems,
            static function ($item) use ($dateRange): bool {
                $createdAt = $item->getCreatedAt();
                if ($createdAt === '') {
                    return false;
                }

                try {
                    $date = new \DateTimeImmutable($createdAt);
                } catch (\Exception) {
                    return false;
                }

                return $dateRange->contains($date);
            },
        ));

        // 6. Render and return
        return $renderer->render($community, $filtered, $dateRange);
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
