<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Community;

use App\Entity\Community\WikiSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WikiSchema::class)]
final class WikiSchemaTest extends TestCase
{
    #[Test]
    public function it_creates_with_defaults(): void
    {
        $schema = new WikiSchema();

        self::assertSame('en', $schema->defaultLanguage);
        self::assertSame([], $schema->knowledgeTypes);
        self::assertSame('', $schema->llmInstructions);
    }

    #[Test]
    public function it_creates_from_array(): void
    {
        $schema = WikiSchema::fromArray([
            'default_language' => 'oji',
            'knowledge_types' => ['Governance', 'Cultural', 'Land'],
            'llm_instructions' => 'Preserve oral history phrasing. Do not paraphrase elders.',
        ]);

        self::assertSame('oji', $schema->defaultLanguage);
        self::assertSame(['Governance', 'Cultural', 'Land'], $schema->knowledgeTypes);
        self::assertSame('Preserve oral history phrasing. Do not paraphrase elders.', $schema->llmInstructions);
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $schema = new WikiSchema(
            defaultLanguage: 'fr',
            knowledgeTypes: ['Event'],
            llmInstructions: 'Respond in French.',
        );

        $array = $schema->toArray();

        self::assertSame('fr', $array['default_language']);
        self::assertSame(['Event'], $array['knowledge_types']);
        self::assertSame('Respond in French.', $array['llm_instructions']);
    }

    #[Test]
    public function it_roundtrips_through_json(): void
    {
        $original = new WikiSchema(
            defaultLanguage: 'oji',
            knowledgeTypes: ['Governance', 'Cultural'],
            llmInstructions: 'Use community voice.',
        );

        $json = json_encode($original->toArray(), JSON_THROW_ON_ERROR);
        $restored = WikiSchema::fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));

        self::assertSame($original->defaultLanguage, $restored->defaultLanguage);
        self::assertSame($original->knowledgeTypes, $restored->knowledgeTypes);
        self::assertSame($original->llmInstructions, $restored->llmInstructions);
    }

    #[Test]
    public function from_array_ignores_unknown_keys(): void
    {
        $schema = WikiSchema::fromArray([
            'default_language' => 'en',
            'unknown_key' => 'ignored',
        ]);

        self::assertSame('en', $schema->defaultLanguage);
    }

    #[Test]
    public function from_array_handles_empty_input(): void
    {
        $schema = WikiSchema::fromArray([]);

        self::assertSame('en', $schema->defaultLanguage);
        self::assertSame([], $schema->knowledgeTypes);
        self::assertSame('', $schema->llmInstructions);
    }
}
