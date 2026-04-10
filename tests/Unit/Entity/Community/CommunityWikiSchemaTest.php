<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Entity\Community;

use Giiken\Entity\Community\Community;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Community::class)]
final class CommunityWikiSchemaTest extends TestCase
{
    #[Test]
    public function get_wiki_schema_returns_typed_object(): void
    {
        $community = Community::make([
            'name' => 'Test Community',
            'slug' => 'test-community',
            'wiki_schema' => [
                'default_language' => 'oji',
                'knowledge_types' => ['Governance', 'Cultural'],
                'llm_instructions' => 'Preserve oral history phrasing.',
            ],
        ]);

        $schema = $community->wikiSchema();

        self::assertSame('oji', $schema->defaultLanguage);
        self::assertSame(['Governance', 'Cultural'], $schema->knowledgeTypes);
        self::assertSame('Preserve oral history phrasing.', $schema->llmInstructions);
    }

    #[Test]
    public function get_typed_wiki_schema_returns_defaults_when_empty(): void
    {
        $community = Community::make(['name' => 'Empty Schema', 'slug' => 'empty-schema']);

        $schema = $community->wikiSchema();

        self::assertSame('en', $schema->defaultLanguage);
        self::assertSame([], $schema->knowledgeTypes);
        self::assertSame('', $schema->llmInstructions);
    }

    #[Test]
    public function get_typed_wiki_schema_handles_json_string(): void
    {
        $schemaJson = json_encode([
            'default_language' => 'fr',
            'knowledge_types' => ['Event'],
            'llm_instructions' => 'Respond in French.',
        ], JSON_THROW_ON_ERROR);

        $community = Community::make([
            'name' => 'JSON Schema',
            'slug' => 'json-schema',
            'wiki_schema' => $schemaJson,
        ]);

        $schema = $community->wikiSchema();

        self::assertSame('fr', $schema->defaultLanguage);
        self::assertSame(['Event'], $schema->knowledgeTypes);
    }
}
