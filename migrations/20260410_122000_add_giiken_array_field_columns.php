<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * {@see EntityRepository} persists the full {@see EntityInterface::toArray()} bag as columns.
 * Json-backed list fields on knowledge items and lint reports need matching SQLite columns.
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $conn = $schema->getConnection();

        if ($schema->hasTable('knowledge_item')) {
            foreach ([
                'allowed_roles'    => "TEXT NOT NULL DEFAULT '[]'",
                'allowed_users'    => "TEXT NOT NULL DEFAULT '[]'",
                'source_media_ids' => "TEXT NOT NULL DEFAULT '[]'",
            ] as $column => $definition) {
                if (!$schema->hasColumn('knowledge_item', $column)) {
                    $conn->executeStatement("ALTER TABLE knowledge_item ADD COLUMN {$column} {$definition}");
                }
            }
        }

        if ($schema->hasTable('wiki_lint_report')) {
            if (!$schema->hasColumn('wiki_lint_report', 'findings')) {
                $conn->executeStatement("ALTER TABLE wiki_lint_report ADD COLUMN findings TEXT NOT NULL DEFAULT '[]'");
            }
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite cannot drop columns cleanly — no-op.
    }
};
