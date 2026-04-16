#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Import curated Sagamok knowledge exports from Minoo into Giiken knowledge_item entities.
 *
 * Usage:
 *   php scripts/import_minoo_sagamok.php --dry-run
 *   php scripts/import_minoo_sagamok.php
 *   php scripts/import_minoo_sagamok.php --dictionary-limit=500
 *   php scripts/import_minoo_sagamok.php --input-dir=/path/to/export
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\KnowledgeType;
use App\Entity\KnowledgeItem\Source\Attribution;
use App\Entity\KnowledgeItem\Source\CopyrightStatus;
use App\Entity\KnowledgeItem\Source\KnowledgeItemSource;
use App\Entity\KnowledgeItem\Source\OriginType;
use App\Entity\KnowledgeItem\Source\Rights;
use App\Entity\KnowledgeItem\Source\SourceOrigin;
use App\Entity\KnowledgeItem\Source\SourceReference;
use Symfony\Component\Uid\Uuid;
use Waaseyaa\Foundation\Kernel\AbstractKernel;
use Waaseyaa\Foundation\Kernel\HttpKernel;

const DEFAULT_INPUT_DIR = '/home/jones/dev/minoo/storage/exports/sagamok';
const DEFAULT_REPORT_DIR = __DIR__ . '/../storage/import-reports';
const TARGET_COMMUNITY_SLUG = 'sagamok-anishnawbek';

$options = getopt('', ['input-dir::', 'dry-run', 'dictionary-limit::']);
$inputDir = isset($options['input-dir']) && is_string($options['input-dir']) && $options['input-dir'] !== ''
    ? $options['input-dir']
    : DEFAULT_INPUT_DIR;
$isDryRun = array_key_exists('dry-run', $options);
$dictionaryLimit = isset($options['dictionary-limit']) ? max(0, (int) $options['dictionary-limit']) : null;

$contentPath = $inputDir . '/curated-content.json';
$dictionaryPath = $inputDir . '/curated-dictionary.json';

if (!is_file($contentPath)) {
    fwrite(STDERR, "Missing curated content file: {$contentPath}\n");
    exit(1);
}
if (!is_file($dictionaryPath)) {
    fwrite(STDERR, "Missing curated dictionary file: {$dictionaryPath}\n");
    exit(1);
}

$contentRows = readCuratedRows($contentPath);
$dictionaryRows = readCuratedRows($dictionaryPath);

if ($dictionaryLimit !== null && $dictionaryLimit > 0) {
    $dictionaryRows = array_slice($dictionaryRows, 0, $dictionaryLimit);
}

$allRows = array_merge($contentRows, $dictionaryRows);
if ($allRows === []) {
    fwrite(STDERR, "No rows to import.\n");
    exit(1);
}

$kernel = new HttpKernel(dirname(__DIR__));
$boot = new ReflectionMethod(AbstractKernel::class, 'boot');
$boot->invoke($kernel);

$entityTypeManager = $kernel->getEntityTypeManager();
$communityStorage = $entityTypeManager->getStorage('community');
$knowledgeStorage = $entityTypeManager->getStorage('knowledge_item');

$communityId = ensureTargetCommunityId($communityStorage, $isDryRun);

$stats = [
    'created' => 0,
    'updated' => 0,
    'skipped' => 0,
    'duplicates' => 0,
    'errors' => 0,
];

$reportRows = [];

foreach ($allRows as $row) {
    if (!is_array($row)) {
        ++$stats['skipped'];
        $reportRows[] = [
            'action' => 'skip',
            'reason' => 'row_not_array',
            'row' => null,
        ];
        continue;
    }

    try {
        $sourceTable = (string) nestedGet($row, ['source', 'table'], '');
        $sourceId = (int) nestedGet($row, ['source', 'id'], 0);
        $fingerprint = fingerprint($sourceTable, $sourceId);

        if ($sourceTable === '' || $sourceId <= 0) {
            ++$stats['skipped'];
            $reportRows[] = [
                'action' => 'skip',
                'reason' => 'missing_source_fingerprint',
                'fingerprint' => $fingerprint,
            ];
            continue;
        }

        $matchingIds = $knowledgeStorage->getQuery()
            ->condition('source_reference_url', $fingerprint)
            ->execute();

        if (count($matchingIds) > 1) {
            ++$stats['duplicates'];
            ++$stats['skipped'];
            $reportRows[] = [
                'action' => 'skip',
                'reason' => 'duplicate_existing_rows',
                'fingerprint' => $fingerprint,
                'matching_ids' => array_values($matchingIds),
            ];
            continue;
        }

        $isExisting = $matchingIds !== [];
        $entity = $isExisting
            ? $knowledgeStorage->load(reset($matchingIds))
            : KnowledgeItem::make([
                'uuid' => Uuid::v4()->toRfc4122(),
                'bundle' => 'knowledge_item',
            ]);

        if (!$entity instanceof KnowledgeItem) {
            ++$stats['errors'];
            $reportRows[] = [
                'action' => 'error',
                'reason' => 'entity_load_failed',
                'fingerprint' => $fingerprint,
            ];
            continue;
        }

        $title = trim((string) ($row['title'] ?? ''));
        $content = trim((string) ($row['content'] ?? ''));
        if ($title === '' || $content === '') {
            ++$stats['skipped'];
            $reportRows[] = [
                'action' => 'skip',
                'reason' => 'empty_title_or_content',
                'fingerprint' => $fingerprint,
            ];
            continue;
        }

        $knowledgeType = normalizeKnowledgeType((string) ($row['knowledge_type'] ?? 'cultural'));
        $createdAt = normalizeIsoTimestamp(nestedGet($row, ['timestamps', 'created_at'], null)) ?? date('c');
        $updatedAt = normalizeIsoTimestamp(nestedGet($row, ['timestamps', 'updated_at'], null)) ?? date('c');

        $entity->set('title', $title);
        $entity->set('bundle', 'knowledge_item');
        $entity->set('slug', makeSlug($title, $fingerprint));
        $entity->set('community_id', (string) $communityId);
        $entity->set('content', $content);
        $entity->set('knowledge_type', $knowledgeType->value);
        $entity->set('access_tier', AccessTier::Public->value);
        $entity->set('created_at', $createdAt);
        $entity->set('updated_at', $updatedAt);

        $tags = nestedGet($row, ['tags'], []);
        $metadata = nestedGet($row, ['metadata'], []);
        $rightsData = nestedGet($row, ['rights'], []);
        $sourceUrl = (string) ($row['source_url'] ?? '');

        if (is_array($tags)) {
            $entity->set('allowed_roles', array_values(array_map(strval(...), $tags)));
        }

        if (is_array($metadata)) {
            $entity->set('allowed_users', [
                json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }

        $copyrightStatus = normalizeCopyrightStatus((string) (is_array($rightsData) ? ($rightsData['copyright_status'] ?? '') : ''));
        $consentPublic = normalizeBool(is_array($rightsData) ? ($rightsData['consent_public'] ?? true) : true, true);
        $consentAi = normalizeBool(is_array($rightsData) ? ($rightsData['consent_ai_training'] ?? false) : false, false);
        $license = is_array($rightsData) && isset($rightsData['license']) ? trim((string) $rightsData['license']) : '';

        $referenceUrl = $fingerprint;
        $attributionCreator = $sourceTable === 'resource_person' ? $title : null;
        $attributionCitation = $sourceUrl !== '' ? $sourceUrl : null;

        $source = new KnowledgeItemSource(
            origin: new SourceOrigin(
                type: OriginType::Wiki,
                ingestedAt: date('c'),
                system: 'minoo-sqlite-export',
                pipelineVersion: 'minoo-sagamok-v1',
            ),
            reference: new SourceReference(
                url: $referenceUrl,
                sourceName: 'minoo',
                externalId: $sourceTable . ':' . $sourceId,
                crawledAt: $updatedAt,
                qualityScore: null,
                contentType: $sourceTable,
            ),
            attribution: new Attribution(
                creator: $attributionCreator,
                publisher: 'Minoo',
                publishedAt: $createdAt,
                citation: $attributionCitation,
            ),
            rights: new Rights(
                copyrightStatus: $copyrightStatus,
                license: $license !== '' ? $license : null,
                consentPublic: $consentPublic,
                consentAiTraining: $consentAi,
            ),
        );
        $entity->setSource($source);

        if ($isDryRun) {
            if ($isExisting) {
                ++$stats['updated'];
                $reportRows[] = [
                    'action' => 'would_update',
                    'fingerprint' => $fingerprint,
                    'knowledge_item_id' => reset($matchingIds),
                    'title' => $title,
                ];
            } else {
                ++$stats['created'];
                $reportRows[] = [
                    'action' => 'would_create',
                    'fingerprint' => $fingerprint,
                    'title' => $title,
                ];
            }
            continue;
        }

        if (!$isExisting) {
            $entity->enforceIsNew(true);
        }
        $knowledgeStorage->save($entity);

        if ($isExisting) {
            ++$stats['updated'];
            $reportRows[] = [
                'action' => 'updated',
                'fingerprint' => $fingerprint,
                'knowledge_item_id' => reset($matchingIds),
                'title' => $title,
            ];
        } else {
            ++$stats['created'];
            $reportRows[] = [
                'action' => 'created',
                'fingerprint' => $fingerprint,
                'knowledge_item_id' => $entity->id(),
                'title' => $title,
            ];
        }
    } catch (Throwable $throwable) {
        ++$stats['errors'];
        $reportRows[] = [
            'action' => 'error',
            'reason' => $throwable->getMessage(),
        ];
    }
}

if (!is_dir(DEFAULT_REPORT_DIR) && !mkdir(DEFAULT_REPORT_DIR, 0775, true) && !is_dir(DEFAULT_REPORT_DIR)) {
    fwrite(STDERR, "Failed to create report directory: " . DEFAULT_REPORT_DIR . "\n");
    exit(1);
}

$report = [
    'imported_at' => date('c'),
    'dry_run' => $isDryRun,
    'input_dir' => realpath($inputDir) ?: $inputDir,
    'community_id' => (string) $communityId,
    'community_slug' => TARGET_COMMUNITY_SLUG,
    'dictionary_limit' => $dictionaryLimit,
    'source_counts' => [
        'content_rows' => count($contentRows),
        'dictionary_rows' => count($dictionaryRows),
        'total_rows' => count($allRows),
    ],
    'stats' => $stats,
    'rows' => $reportRows,
];

$reportPath = DEFAULT_REPORT_DIR . '/minoo-sagamok-import-' . date('Ymd-His') . ($isDryRun ? '-dry-run' : '') . '.json';
file_put_contents(
    $reportPath,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n",
);

echo $isDryRun ? "Dry-run complete.\n" : "Import complete.\n";
echo "- Community ID: {$communityId}\n";
echo "- Created: {$stats['created']}\n";
echo "- Updated: {$stats['updated']}\n";
echo "- Skipped: {$stats['skipped']}\n";
echo "- Duplicates: {$stats['duplicates']}\n";
echo "- Errors: {$stats['errors']}\n";
echo "- Report: {$reportPath}\n";

/**
 * @return list<array<string, mixed>>
 */
function readCuratedRows(string $path): array
{
    $raw = file_get_contents($path);
    if (!is_string($raw)) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $rows = [];
    foreach ($decoded as $row) {
        if (is_array($row)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * @param array<string, mixed> $storage
 */
function ensureTargetCommunityId(object $communityStorage, bool $dryRun): int
{
    $matching = $communityStorage->getQuery()
        ->condition('slug', TARGET_COMMUNITY_SLUG)
        ->range(0, 1)
        ->execute();

    if ($matching !== []) {
        return (int) reset($matching);
    }

    if ($dryRun) {
        return -1;
    }

    $community = $communityStorage->create([
        'uuid' => Uuid::v4()->toRfc4122(),
        'bundle' => 'community',
        'name' => 'Sagamok Anishnawbek',
        'slug' => TARGET_COMMUNITY_SLUG,
        'locale' => 'en',
        'wiki_schema' => json_encode([
            'defaultLanguage' => 'en',
            'knowledgeTypes' => array_map(static fn (KnowledgeType $type): string => $type->value, KnowledgeType::cases()),
            'llmInstructions' => 'Preserve community context and provenance for all imported records.',
        ], JSON_THROW_ON_ERROR),
        'created_at' => date('c'),
        'updated_at' => date('c'),
    ]);
    $community->enforceIsNew(true);
    $communityStorage->save($community);

    return (int) $community->id();
}

/**
 * @param array<string, mixed> $row
 */
function nestedGet(array $row, array $path, mixed $default = null): mixed
{
    $cursor = $row;
    foreach ($path as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return $default;
        }
        $cursor = $cursor[$segment];
    }
    return $cursor;
}

function normalizeKnowledgeType(string $value): KnowledgeType
{
    return match ($value) {
        KnowledgeType::Event->value => KnowledgeType::Event,
        KnowledgeType::Governance->value => KnowledgeType::Governance,
        KnowledgeType::Land->value => KnowledgeType::Land,
        KnowledgeType::Relationship->value => KnowledgeType::Relationship,
        KnowledgeType::Synthesis->value => KnowledgeType::Synthesis,
        default => KnowledgeType::Cultural,
    };
}

function normalizeCopyrightStatus(string $value): CopyrightStatus
{
    return match ($value) {
        CopyrightStatus::Owned->value => CopyrightStatus::Owned,
        CopyrightStatus::Licensed->value => CopyrightStatus::Licensed,
        CopyrightStatus::FairDealing->value => CopyrightStatus::FairDealing,
        CopyrightStatus::PublicDomain->value => CopyrightStatus::PublicDomain,
        default => CopyrightStatus::ExternalLink,
    };
}

function normalizeBool(mixed $value, bool $default): bool
{
    if ($value === null || $value === '') {
        return $default;
    }
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }
    $normalized = strtolower(trim((string) $value));
    if (in_array($normalized, ['1', 'true', 'yes'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no'], true)) {
        return false;
    }
    return $default;
}

function normalizeIsoTimestamp(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        $unix = (int) $value;
        return $unix > 0 ? gmdate('c', $unix) : null;
    }

    $text = trim((string) $value);
    if ($text === '') {
        return null;
    }

    $parsed = strtotime($text);
    if ($parsed === false) {
        return null;
    }

    return gmdate('c', $parsed);
}

function fingerprint(string $sourceTable, int $sourceId): string
{
    return sprintf('minoo://%s/%d', $sourceTable, $sourceId);
}

function makeSlug(string $title, string $fingerprint): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
    $slug = trim((string) $slug, '-');

    if ($slug === '') {
        $slug = 'imported-item';
    }

    return $slug . '-' . substr(sha1($fingerprint), 0, 8);
}
