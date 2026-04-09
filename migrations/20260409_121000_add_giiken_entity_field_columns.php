<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * EntityRepository + SqlStorageDriver persist every field in toArray() as real columns.
 * Add columns for Giiken domain fields (extras are not auto-packed into _data yet).
 */
return new class extends Migration
{
    /** @var array<string, array<string, string>> table => column => SQL type/def */
    private const COLUMNS = [
        'community' => [
            'slug'          => "VARCHAR(255) NOT NULL DEFAULT ''",
            'wiki_schema'   => "TEXT NOT NULL DEFAULT '{}'",
            'locale'        => "VARCHAR(12) NOT NULL DEFAULT 'en'",
            'created_at'    => "TEXT NOT NULL DEFAULT ''",
            'updated_at'    => "TEXT NOT NULL DEFAULT ''",
            'sovereignty_profile' => "VARCHAR(32) NOT NULL DEFAULT 'local'",
            'contact_email' => "VARCHAR(255) NOT NULL DEFAULT ''",
        ],
        'knowledge_item' => [
            'community_id'   => "VARCHAR(128) NOT NULL DEFAULT ''",
            'content'        => "TEXT NOT NULL DEFAULT ''",
            'knowledge_type' => "VARCHAR(64) NOT NULL DEFAULT ''",
            'access_tier'    => "VARCHAR(32) NOT NULL DEFAULT 'members'",
            'created_at'     => "TEXT NOT NULL DEFAULT ''",
            'updated_at'     => "TEXT NOT NULL DEFAULT ''",
            'compiled_at'    => "TEXT NOT NULL DEFAULT ''",
        ],
        'wiki_lint_report' => [
            'community_id' => "VARCHAR(128) NOT NULL DEFAULT ''",
            'created_at'   => "TEXT NOT NULL DEFAULT ''",
            'updated_at'   => "TEXT NOT NULL DEFAULT ''",
        ],
    ];

    public function up(SchemaBuilder $schema): void
    {
        $conn = $schema->getConnection();

        foreach (self::COLUMNS as $table => $columns) {
            if (!$schema->hasTable($table)) {
                continue;
            }
            foreach ($columns as $column => $definition) {
                if ($schema->hasColumn($table, $column)) {
                    continue;
                }
                $conn->executeStatement("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        // SQLite cannot drop columns cleanly — no-op (dev databases: rebuild from scratch).
    }
};
