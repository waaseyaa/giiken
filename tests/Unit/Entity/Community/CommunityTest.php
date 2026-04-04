<?php

declare(strict_types=1);

namespace Giiken\Tests\Unit\Entity\Community;

use Giiken\Entity\Community\Community;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Community::class)]
final class CommunityTest extends TestCase
{
    #[Test]
    public function it_sets_default_locale_and_created_at(): void
    {
        $community = new Community(['name' => 'Sagamok Anishnawbek', 'slug' => 'sagamok']);

        $this->assertSame('en', $community->getLocale());
        $this->assertNotEmpty($community->getCreatedAt());
    }

    #[Test]
    public function it_returns_provided_fields(): void
    {
        $community = new Community([
            'name'                => 'Sagamok Anishnawbek',
            'slug'                => 'sagamok',
            'sovereignty_profile' => 'local',
            'locale'              => 'oj',
            'contact_email'       => 'admin@sagamok.ca',
        ]);

        $this->assertSame('Sagamok Anishnawbek', $community->getName());
        $this->assertSame('sagamok', $community->getSlug());
        $this->assertSame('local', $community->getSovereigntyProfile());
        $this->assertSame('oj', $community->getLocale());
        $this->assertSame('admin@sagamok.ca', $community->getContactEmail());
    }

    #[Test]
    public function sovereignty_profile_rejects_invalid_values(): void
    {
        $community = new Community([
            'name'                => 'Test',
            'slug'                => 'test',
            'sovereignty_profile' => 'invalid_value',
        ]);

        $this->assertSame('local', $community->getSovereigntyProfile());
    }

    #[Test]
    public function it_accepts_all_valid_sovereignty_profiles(): void
    {
        foreach (Community::SOVEREIGNTY_PROFILES as $profile) {
            $community = new Community([
                'name'                => 'Test',
                'slug'                => 'test',
                'sovereignty_profile' => $profile,
            ]);

            $this->assertSame($profile, $community->getSovereigntyProfile());
        }
    }

    #[Test]
    public function wiki_schema_returns_empty_array_when_not_set(): void
    {
        $community = new Community(['name' => 'Test', 'slug' => 'test']);

        $this->assertSame([], $community->getWikiSchema());
    }

    #[Test]
    public function wiki_schema_decodes_json_string(): void
    {
        $schema = ['default_language' => 'oj', 'llm_instructions' => 'Use Ojibwe terms.'];
        $community = new Community([
            'name'        => 'Test',
            'slug'        => 'test',
            'wiki_schema' => json_encode($schema, JSON_THROW_ON_ERROR),
        ]);

        $this->assertSame($schema, $community->getWikiSchema());
    }

    #[Test]
    public function wiki_schema_returns_empty_array_on_corrupt_json(): void
    {
        $community = new Community([
            'name'        => 'Test',
            'slug'        => 'test',
            'wiki_schema' => '{not valid json',
        ]);

        $this->assertSame([], $community->getWikiSchema());
    }
}
