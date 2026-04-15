<?php

declare(strict_types=1);

namespace App\Ingestion\NorthCloud;

use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeType;
use App\Entity\KnowledgeItem\Source\Attribution;
use App\Entity\KnowledgeItem\Source\CopyrightStatus;
use App\Entity\KnowledgeItem\Source\KnowledgeItemSource;
use App\Entity\KnowledgeItem\Source\OriginType;
use App\Entity\KnowledgeItem\Source\Rights;
use App\Entity\KnowledgeItem\Source\SourceOrigin;
use App\Entity\KnowledgeItem\Source\SourceReference;
use Waaseyaa\Foundation\SlugGenerator;
use Waaseyaa\NorthCloud\Sync\NcHitToEntityMapperInterface;

/**
 * Map North Cloud search hits to {@see KnowledgeItem} rows.
 *
 * Every hit becomes a Public/Cultural knowledge item under a configured
 * community. Consent-for-AI-training is off by default — external content is
 * indexed for search/linkage but not fed to embedding/LLM pipelines without
 * explicit community approval.
 *
 * Dedup is by `source_reference_url` (indexed column mirrored from the
 * structured source blob).
 */
final readonly class NcHitToKnowledgeItemMapper implements NcHitToEntityMapperInterface
{
    public function __construct(
        private string $defaultCommunityId,
        private string $pipelineVersion = '0.1.0',
    ) {}

    public function entityType(): string
    {
        return 'knowledge_item';
    }

    public function dedupField(): string
    {
        return 'source_reference_url';
    }

    public function supports(array $hit): bool
    {
        $url = (string) ($hit['url'] ?? '');
        if ($url === '') {
            return false;
        }

        $topics = $hit['topics'] ?? [];
        if (!is_array($topics)) {
            return false;
        }

        // Only pull items explicitly tagged as indigenous content.
        return in_array('indigenous', array_map(strval(...), $topics), true);
    }

    /**
     * @param array<string, mixed> $hit
     * @return array<string, mixed>
     */
    public function map(array $hit): array
    {
        $title = (string) ($hit['title'] ?? '');
        $body = (string) ($hit['snippet'] ?? $hit['body'] ?? '');

        $source = new KnowledgeItemSource(
            origin: new SourceOrigin(
                type: OriginType::NorthCloud,
                ingestedAt: date('c'),
                system: 'north-cloud-api-v1',
                pipelineVersion: $this->pipelineVersion,
            ),
            reference: new SourceReference(
                url: (string) ($hit['url'] ?? ''),
                sourceName: self::stringOrNull($hit['source_name'] ?? null),
                externalId: self::stringOrNull($hit['id'] ?? null),
                crawledAt: self::stringOrNull($hit['crawled_at'] ?? null),
                qualityScore: isset($hit['quality_score']) ? (int) $hit['quality_score'] : null,
                contentType: self::stringOrNull($hit['content_type'] ?? null),
            ),
            attribution: new Attribution(
                creator: self::stringOrNull($hit['author'] ?? null),
                publisher: self::stringOrNull($hit['source_name'] ?? null),
                publishedAt: self::stringOrNull($hit['published_date'] ?? null),
            ),
            rights: new Rights(
                copyrightStatus: CopyrightStatus::ExternalLink,
                consentPublic: true,
                consentAiTraining: false,
            ),
        );

        // The entity's $casts + sanitize handle source JSON serialization; we
        // also project the indexed columns here so the repository save-path
        // writes them without needing a post-save hook.
        $indexed = $source->indexedColumns();

        return [
            'title' => $title,
            'slug' => SlugGenerator::generate($title !== '' ? $title : 'nc-' . ($hit['id'] ?? uniqid())),
            'community_id' => $this->defaultCommunityId,
            'knowledge_type' => KnowledgeType::Cultural->value,
            'access_tier' => AccessTier::Public->value,
            'content' => $body,
            'source' => json_encode($source->toArray(), JSON_THROW_ON_ERROR),
            'source_origin_type' => $indexed['source_origin_type'],
            'source_reference_url' => $indexed['source_reference_url'],
            'source_ingested_at' => $indexed['source_ingested_at'],
            'rights_license' => $indexed['rights_license'],
            'created_at' => self::parseTimestamp($hit['published_date'] ?? null),
            'updated_at' => date('c'),
        ];
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string) $value;
    }

    private static function parseTimestamp(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);
            if ($ts !== false) {
                return date('c', $ts);
            }
        }

        return date('c');
    }
}
