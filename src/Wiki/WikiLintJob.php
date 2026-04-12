<?php

declare(strict_types=1);

namespace App\Wiki;

use App\Wiki\Check\LintCheckInterface;
use Waaseyaa\Entity\Repository\EntityRepositoryInterface;

final class WikiLintJob
{
    /**
     * @param list<LintCheckInterface> $checks
     */
    public function __construct(
        private readonly string $communityId,
        private readonly EntityRepositoryInterface $repository,
        private readonly array $checks,
    ) {}

    public function handle(): void
    {
        /** @var list<\App\Entity\KnowledgeItem\KnowledgeItem> $items */
        $items = $this->repository->findBy([
            'community_id' => $this->communityId,
        ]);

        $allFindings = [];

        foreach ($this->checks as $check) {
            $findings = $check->run($items);
            foreach ($findings as $finding) {
                $allFindings[] = $finding;
            }
        }

        $report = WikiLintReport::make([
            'id' => 'lint-report-' . $this->communityId . '-' . time(),
            'title' => 'Wiki Lint Report — ' . date('Y-m-d H:i'),
            'community_id' => $this->communityId,
            'findings' => $allFindings,
        ]);

        $this->repository->save($report);
    }
}
