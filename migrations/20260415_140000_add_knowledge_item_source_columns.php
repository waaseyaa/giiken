<?php

declare(strict_types=1);

use App\Entity\KnowledgeItem\Source\KnowledgeItemSource;
use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Introduce structured provenance on knowledge_item.
 *
 * Adds a JSON `source` column for the full {@see KnowledgeItemSource} shape plus
 * four indexed columns mirroring the hot fields (origin type, reference URL,
 * ingested_at, license). Existing rows are backfilled with origin.type=`manual`
 * so every row has a valid shape after this migration runs.
 *
 * See docs/architecture/knowledge-item-source.md for the full rationale.
 */
return new class extends Migration {
    public function up(SchemaBuilder $schema): void
    {
        $conn = $schema->getConnection();

        if (!$schema->hasTable('knowledge_item')) {
            return;
        }

        $columns = [
            'source' => "TEXT NOT NULL DEFAULT '{}'",
            'source_origin_type' => "TEXT NOT NULL DEFAULT 'manual'",
            'source_reference_url' => 'TEXT NULL',
            'source_ingested_at' => 'TEXT NULL',
            'rights_license' => 'TEXT NULL',
        ];

        foreach ($columns as $column => $definition) {
            if (!$schema->hasColumn('knowledge_item', $column)) {
                $conn->executeStatement(
                    "ALTER TABLE knowledge_item ADD COLUMN {$column} {$definition}",
                );
            }
        }

        $indexes = [
            'idx_knowledge_item_source_origin_type' => 'source_origin_type',
            'idx_knowledge_item_source_reference_url' => 'source_reference_url',
            'idx_knowledge_item_source_ingested_at' => 'source_ingested_at',
            'idx_knowledge_item_rights_license' => 'rights_license',
        ];

        foreach ($indexes as $indexName => $column) {
            $conn->executeStatement(
                "CREATE INDEX IF NOT EXISTS {$indexName} ON knowledge_item ({$column})",
            );
        }

        $this->backfill($conn);
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite cannot drop columns cleanly — no-op.
    }

    /**
     * Set a valid manual-origin source on every row that still has the default
     * empty object. Each row's `created_at` becomes the `ingested_at` so
     * historical ordering is preserved.
     */
    private function backfill(object $conn): void
    {
        $rows = $conn->fetchAllAssociative(
            "SELECT id, created_at FROM knowledge_item WHERE source = '{}' OR source IS NULL",
        );

        if (!is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $createdAt = (string) ($row['created_at'] ?? '');
            if ($id === null) {
                continue;
            }

            $source = KnowledgeItemSource::manualDefault($createdAt !== '' ? $createdAt : null);
            $json = json_encode($source->toArray(), JSON_THROW_ON_ERROR);
            $indexed = $source->indexedColumns();

            $conn->executeStatement(
                'UPDATE knowledge_item SET '
                . 'source = :source, '
                . 'source_origin_type = :origin_type, '
                . 'source_reference_url = :reference_url, '
                . 'source_ingested_at = :ingested_at, '
                . 'rights_license = :license '
                . 'WHERE id = :id',
                [
                    'source' => $json,
                    'origin_type' => $indexed['source_origin_type'],
                    'reference_url' => $indexed['source_reference_url'],
                    'ingested_at' => $indexed['source_ingested_at'],
                    'license' => $indexed['rights_license'],
                    'id' => $id,
                ],
            );
        }
    }
};
