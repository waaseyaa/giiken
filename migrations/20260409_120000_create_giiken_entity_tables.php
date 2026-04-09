<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

/**
 * Materialize Giiken content entity tables (matches SqlSchemaHandler content-entity layout).
 */
return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        $conn = $schema->getConnection();

        if (!$schema->hasTable('community')) {
            $conn->executeStatement("
                CREATE TABLE community (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid VARCHAR(128) NOT NULL DEFAULT '',
                    bundle VARCHAR(128) NOT NULL DEFAULT '',
                    name VARCHAR(255) NOT NULL DEFAULT '',
                    langcode VARCHAR(12) NOT NULL DEFAULT 'en',
                    _data TEXT NOT NULL DEFAULT '{}'
                )
            ");
            $conn->executeStatement('CREATE UNIQUE INDEX community_uuid ON community(uuid)');
            $conn->executeStatement('CREATE INDEX community_bundle ON community(bundle)');
        }

        if (!$schema->hasTable('knowledge_item')) {
            $conn->executeStatement("
                CREATE TABLE knowledge_item (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid VARCHAR(128) NOT NULL DEFAULT '',
                    bundle VARCHAR(128) NOT NULL DEFAULT '',
                    title VARCHAR(255) NOT NULL DEFAULT '',
                    langcode VARCHAR(12) NOT NULL DEFAULT 'en',
                    _data TEXT NOT NULL DEFAULT '{}'
                )
            ");
            $conn->executeStatement('CREATE UNIQUE INDEX knowledge_item_uuid ON knowledge_item(uuid)');
            $conn->executeStatement('CREATE INDEX knowledge_item_bundle ON knowledge_item(bundle)');
        }

        if (!$schema->hasTable('wiki_lint_report')) {
            $conn->executeStatement("
                CREATE TABLE wiki_lint_report (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    uuid VARCHAR(128) NOT NULL DEFAULT '',
                    bundle VARCHAR(128) NOT NULL DEFAULT '',
                    title VARCHAR(255) NOT NULL DEFAULT '',
                    langcode VARCHAR(12) NOT NULL DEFAULT 'en',
                    _data TEXT NOT NULL DEFAULT '{}'
                )
            ");
            $conn->executeStatement('CREATE UNIQUE INDEX wiki_lint_report_uuid ON wiki_lint_report(uuid)');
            $conn->executeStatement('CREATE INDEX wiki_lint_report_bundle ON wiki_lint_report(bundle)');
        }
    }

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('wiki_lint_report');
        $schema->dropIfExists('knowledge_item');
        $schema->dropIfExists('community');
    }
};
