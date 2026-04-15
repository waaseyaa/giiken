<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\KnowledgeItem\Source;

use App\Entity\KnowledgeItem\Source\Attribution;
use App\Entity\KnowledgeItem\Source\CopyrightStatus;
use App\Entity\KnowledgeItem\Source\KnowledgeItemSource;
use App\Entity\KnowledgeItem\Source\OriginType;
use App\Entity\KnowledgeItem\Source\Rights;
use App\Entity\KnowledgeItem\Source\SourceOrigin;
use App\Entity\KnowledgeItem\Source\SourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(KnowledgeItemSource::class)]
#[CoversClass(SourceOrigin::class)]
#[CoversClass(SourceReference::class)]
#[CoversClass(Attribution::class)]
#[CoversClass(Rights::class)]
final class KnowledgeItemSourceTest extends TestCase
{
    #[Test]
    public function manualDefaultHasManualOriginAndPermissiveRights(): void
    {
        $source = KnowledgeItemSource::manualDefault('2026-04-15T00:00:00+00:00');

        $this->assertSame(OriginType::Manual, $source->origin->type);
        $this->assertSame('2026-04-15T00:00:00+00:00', $source->origin->ingestedAt);
        $this->assertTrue($source->reference->isEmpty());
        $this->assertTrue($source->attribution->isEmpty());
        $this->assertSame(CopyrightStatus::ExternalLink, $source->rights->copyrightStatus);
        $this->assertTrue($source->rights->consentPublic);
        $this->assertFalse($source->rights->consentAiTraining, 'AI-training consent must be off by default');
    }

    #[Test]
    public function roundtripsThroughArray(): void
    {
        $source = new KnowledgeItemSource(
            origin: new SourceOrigin(
                type: OriginType::NorthCloud,
                ingestedAt: '2026-04-15T12:00:00+00:00',
                system: 'nc-api-v1',
                pipelineVersion: '0.1.0',
            ),
            reference: new SourceReference(
                url: 'https://example.test/article',
                sourceName: 'Example News',
                externalId: 'nc-abc123',
                crawledAt: '2026-04-14T10:00:00+00:00',
                qualityScore: 82,
                contentType: 'article',
            ),
            attribution: new Attribution(
                creator: 'Jane Doe',
                publisher: 'Example News',
                publishedAt: '2026-04-13',
                citation: 'Doe, J. (2026). Title.',
            ),
            rights: new Rights(
                copyrightStatus: CopyrightStatus::Licensed,
                license: 'CC-BY-4.0',
                consentPublic: true,
                consentAiTraining: false,
                tkLabels: ['TK Attribution', 'TK Non-Commercial'],
                careFlags: ['authority_to_control' => 'community-123'],
            ),
        );

        $roundtripped = KnowledgeItemSource::fromArray($source->toArray());

        $this->assertEquals($source, $roundtripped);
    }

    #[Test]
    public function indexedColumnsMirrorHotFields(): void
    {
        $source = new KnowledgeItemSource(
            origin: new SourceOrigin(type: OriginType::NorthCloud, ingestedAt: '2026-04-15T00:00:00+00:00'),
            reference: new SourceReference(url: 'https://example.test/x'),
            attribution: new Attribution(),
            rights: new Rights(copyrightStatus: CopyrightStatus::Licensed, license: 'CC-BY-4.0'),
        );

        $this->assertSame([
            'source_origin_type' => 'northcloud',
            'source_reference_url' => 'https://example.test/x',
            'source_ingested_at' => '2026-04-15T00:00:00+00:00',
            'rights_license' => 'CC-BY-4.0',
        ], $source->indexedColumns());
    }

    #[Test]
    public function fromArrayFallsBackToManualOriginWhenMissing(): void
    {
        $source = KnowledgeItemSource::fromArray([]);

        $this->assertSame(OriginType::Manual, $source->origin->type);
        $this->assertSame(CopyrightStatus::ExternalLink, $source->rights->copyrightStatus);
    }

    #[Test]
    public function fromArrayTolleratesUnknownEnumValues(): void
    {
        $source = KnowledgeItemSource::fromArray([
            'origin' => ['type' => 'not-a-real-origin', 'ingested_at' => '2026-01-01T00:00:00+00:00'],
            'rights' => ['copyright_status' => 'not-a-real-status'],
        ]);

        $this->assertSame(OriginType::Manual, $source->origin->type);
        $this->assertSame(CopyrightStatus::ExternalLink, $source->rights->copyrightStatus);
    }

    #[Test]
    public function toArrayOmitsEmptyReferenceAndAttribution(): void
    {
        $source = KnowledgeItemSource::manualDefault('2026-04-15T00:00:00+00:00');

        $arr = $source->toArray();

        $this->assertArrayHasKey('origin', $arr);
        $this->assertArrayHasKey('rights', $arr);
        $this->assertArrayNotHasKey('reference', $arr);
        $this->assertArrayNotHasKey('attribution', $arr);
    }
}
