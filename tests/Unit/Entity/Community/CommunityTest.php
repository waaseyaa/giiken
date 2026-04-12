<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Community;

use App\Entity\Community\Community;
use App\Entity\Community\WikiSchema;
use App\Entity\Community\SovereigntyProfile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Community::class)]
final class CommunityTest extends TestCase
{
    #[Test]
    public function it_sets_default_locale_and_created_at(): void
    {
        $community = Community::make(['name' => 'Sagamok Anishnawbek', 'slug' => 'sagamok']);

        $this->assertSame('en', $community->locale());
        $this->assertNotNull($community->createdAt());
    }

    #[Test]
    public function it_returns_provided_fields(): void
    {
        $community = Community::make([
            'name'                => 'Sagamok Anishnawbek',
            'slug'                => 'sagamok',
            'sovereignty_profile' => 'local',
            'locale'              => 'oj',
            'contact_email'       => 'admin@sagamok.ca',
        ]);

        $this->assertSame('Sagamok Anishnawbek', $community->name());
        $this->assertSame('sagamok', $community->slug());
        $this->assertSame(SovereigntyProfile::Local, $community->sovereigntyProfile());
        $this->assertSame('oj', $community->locale());
        $this->assertSame('admin@sagamok.ca', $community->contactEmail());
    }

    #[Test]
    public function sovereignty_profile_rejects_invalid_values(): void
    {
        $community = Community::make([
            'name'                => 'Test',
            'slug'                => 'test',
            'sovereignty_profile' => 'invalid_value',
        ]);

        $this->assertSame(SovereigntyProfile::Local, $community->sovereigntyProfile());
    }

    #[Test]
    public function it_accepts_all_valid_sovereignty_profiles(): void
    {
        foreach (SovereigntyProfile::cases() as $profile) {
            $community = Community::make([
                'name'                => 'Test',
                'slug'                => 'test',
                'sovereignty_profile' => $profile->value,
            ]);

            $this->assertSame($profile, $community->sovereigntyProfile());
        }
    }

    #[Test]
    public function wiki_schema_returns_empty_typed_schema_when_not_set(): void
    {
        $community = Community::make(['name' => 'Test', 'slug' => 'test']);

        $this->assertNull($community->get('wiki_schema'));
        $this->assertEquals(WikiSchema::fromArray([]), $community->wikiSchema());
    }

    #[Test]
    public function wiki_schema_decodes_json_string(): void
    {
        $schema = ['default_language' => 'oj', 'llm_instructions' => 'Use Ojibwe terms.'];
        $community = Community::make([
            'name'        => 'Test',
            'slug'        => 'test',
            'wiki_schema' => json_encode($schema, JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame($schema, $community->get('wiki_schema'));
    }

    #[Test]
    public function wiki_schema_corrupt_json_surfaces_via_cast_exception_or_empty(): void
    {
        $this->expectException(\Throwable::class);
        $community = Community::make([
            'name'        => 'Test',
            'slug'        => 'test',
            'wiki_schema' => '{not valid json',
        ]);
        $community->get('wiki_schema');
    }

    #[Test]
    public function make_does_not_throw_when_created_at_is_unparseable(): void
    {
        $community = Community::make([
            'name'        => 'Test',
            'slug'        => 'test',
            'created_at'  => 'not-a-real-datetime',
        ]);

        $this->assertSame(
            0,
            $community->createdAt()->getTimestamp(),
            'Corrupt created_at is coerced to Unix epoch so hydration and casts succeed.',
        );
        $community->get('created_at');
    }

    #[Test]
    public function make_does_not_throw_when_updated_at_is_unparseable(): void
    {
        $community = Community::make([
            'name'       => 'Test',
            'slug'       => 'test',
            'updated_at' => '%%%invalid%%%',
        ]);

        $this->assertNull($community->updatedAt());
        $this->assertNull($community->get('updated_at'));
    }
}
