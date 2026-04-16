<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ingestion\NorthCloud;

use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeType;
use App\Entity\KnowledgeItem\Source\CopyrightStatus;
use App\Entity\KnowledgeItem\Source\KnowledgeItemSource;
use App\Entity\KnowledgeItem\Source\OriginType;
use App\Ingestion\NorthCloud\NcHitToKnowledgeItemMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(NcHitToKnowledgeItemMapper::class)]
final class NcHitToKnowledgeItemMapperTest extends TestCase
{
    #[Test]
    public function supportsRequiresIndigenousTopicAndUrl(): void
    {
        $mapper = new NcHitToKnowledgeItemMapper(defaultCommunityId: 'c1');

        $this->assertTrue($mapper->supports([
            'url' => 'https://example.test/sudbury-a',
            'topics' => ['indigenous', 'governance'],
            'title' => 'Sudbury governance update',
        ]));

        $this->assertTrue($mapper->supports([
            'url' => 'https://example.test/robinson-huron-update',
            'topics' => ['indigenous', 'services'],
            'title' => 'Robinson Huron Treaty territory infrastructure update',
        ]), 'related treaty-region signal passes strict regional filter');

        $this->assertFalse(
            $mapper->supports(['url' => 'https://example.test/a', 'topics' => ['news']]),
            'non-indigenous topic is ignored',
        );
        $this->assertFalse(
            $mapper->supports(['url' => '', 'topics' => ['indigenous']]),
            'missing url is ignored',
        );
        $this->assertFalse(
            $mapper->supports(['url' => 'https://example.test/a']),
            'missing topics is ignored',
        );
        $this->assertFalse(
            $mapper->supports([
                'url' => 'https://example.test/a',
                'topics' => ['indigenous'],
                'title' => 'National Indigenous arts roundup',
            ]),
            'indigenous-only but non-regional stories are ignored',
        );
    }

    #[Test]
    public function diagnoseSupportReturnsReasonAndSignalDetails(): void
    {
        $mapper = new NcHitToKnowledgeItemMapper(defaultCommunityId: 'c1');

        $missingUrl = $mapper->diagnoseSupport([
            'topics' => ['indigenous'],
            'title' => 'Sagamok update',
        ]);
        $this->assertFalse($missingUrl['supported']);
        $this->assertSame('missing_url', $missingUrl['reason']);

        $supported = $mapper->diagnoseSupport([
            'url' => 'https://example.test/robinson-huron-treaty',
            'topics' => ['indigenous'],
            'title' => 'Robinson Huron Treaty territory infrastructure update',
        ]);
        $this->assertTrue($supported['supported']);
        $this->assertContains($supported['details']['matched_term'] ?? null, ['robinson-huron', 'robinson huron treaty']);
        $this->assertContains($supported['details']['matched_strength'] ?? null, ['strong', 'related']);
    }

    #[Test]
    public function mapProducesFullKnowledgeItemSource(): void
    {
        $mapper = new NcHitToKnowledgeItemMapper(defaultCommunityId: 'c1', pipelineVersion: '0.1.0');

        $fields = $mapper->map([
            'id' => 'nc-xyz',
            'title' => 'A Good Article',
            'snippet' => 'Summary text',
            'url' => 'https://example.test/article',
            'source_name' => 'Example News',
            'crawled_at' => '2026-04-14T10:00:00+00:00',
            'quality_score' => 82,
            'content_type' => 'article',
            'author' => 'Jane Doe',
            'published_date' => '2026-04-13',
            'topics' => ['indigenous'],
        ]);

        $this->assertSame('A Good Article', $fields['title']);
        $this->assertSame('Summary text', $fields['content']);
        $this->assertSame('c1', $fields['community_id']);
        $this->assertSame(KnowledgeType::Cultural->value, $fields['knowledge_type']);
        $this->assertSame(AccessTier::Public->value, $fields['access_tier']);
        $this->assertSame('northcloud', $fields['source_origin_type']);
        $this->assertSame('https://example.test/article', $fields['source_reference_url']);

        $source = KnowledgeItemSource::fromArray(json_decode($fields['source'], true));
        $this->assertSame(OriginType::NorthCloud, $source->origin->type);
        $this->assertSame('0.1.0', $source->origin->pipelineVersion);
        $this->assertSame('Jane Doe', $source->attribution->creator);
        $this->assertSame(82, $source->reference->qualityScore);
        $this->assertSame(CopyrightStatus::ExternalLink, $source->rights->copyrightStatus);
        $this->assertFalse($source->rights->consentAiTraining);
    }

    #[Test]
    public function dedupFieldMatchesIndexedColumn(): void
    {
        $mapper = new NcHitToKnowledgeItemMapper(defaultCommunityId: 'c1');

        $this->assertSame('source_reference_url', $mapper->dedupField());
    }
}
