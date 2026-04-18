<?php

declare(strict_types=1);

use Waaseyaa\Foundation\Migration\Migration;
use Waaseyaa\Foundation\Migration\SchemaBuilder;

return new class extends Migration
{
    public function up(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('community')) {
            return;
        }

        $conn = $schema->getConnection();
        $duplicates = $conn->fetchAllAssociative(
            'SELECT slug, COUNT(*) AS duplicate_count FROM community WHERE slug <> ? GROUP BY slug HAVING COUNT(*) > 1',
            [''],
        );

        if ($duplicates !== []) {
            $slugs = array_map(
                static fn (array $row): string => (string) ($row['slug'] ?? ''),
                $duplicates,
            );

            throw new RuntimeException(
                'Cannot add unique index to community.slug; duplicate slugs exist: ' . implode(', ', $slugs),
            );
        }

        $conn->executeStatement('CREATE UNIQUE INDEX IF NOT EXISTS community_slug_unique ON community(slug)');
    }

    public function down(SchemaBuilder $schema): void
    {
        if (!$schema->hasTable('community')) {
            return;
        }

        $schema->getConnection()->executeStatement('DROP INDEX IF EXISTS community_slug_unique');
    }
};
