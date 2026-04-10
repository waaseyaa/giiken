<?php

declare(strict_types=1);

namespace Giiken\Tests\Integration\Entity;

use Carbon\CarbonImmutable;
use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\SovereigntyProfile;
use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\Tests\Integration\Support\GiikenKernelIntegrationTestCase;
use Giiken\Wiki\WikiLintReport;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;
use Waaseyaa\Database\DBALDatabase;
use Waaseyaa\Entity\EntityInterface;
use Waaseyaa\Entity\Hydration\HydrationContext;
use Waaseyaa\EntityStorage\Hydration\EntityInstantiator;

#[CoversNothing]
final class ContentEntitySqlIntegrationTest extends GiikenKernelIntegrationTestCase
{
    #[Test]
    public function community_row_hydrates_via_repository_instantiator_and_casts(): void
    {
        $uuid = Uuid::v4()->toRfc4122();
        $created = '2026-03-10T15:30:00+00:00';
        $updated = '2026-03-11T18:00:00+00:00';

        $entity = Community::make([
            'uuid'                  => $uuid,
            'name'                  => 'Test Nation',
            'slug'                  => 'test-nation',
            'sovereignty_profile'   => SovereigntyProfile::SelfHosted->value,
            'locale'                => 'en',
            'contact_email'         => 'nation@example.org',
            'wiki_schema'           => ['default_language' => 'en'],
            'created_at'            => $created,
            'updated_at'            => $updated,
        ]);
        $entity->enforceIsNew(true);

        $repo = self::entityRepositoryFor('community');
        $repo->save($entity);

        $loaded = self::assertFirstByUuid($repo, $uuid);
        self::assertInstanceOf(Community::class, $loaded);
        self::assertNotSame('', (string) $loaded->id());

        $this->assertNoCastException(static function () use ($loaded): void {
            self::assertInstanceOf(CarbonImmutable::class, $loaded->createdAt());
            self::assertInstanceOf(CarbonImmutable::class, $loaded->updatedAt());
            self::assertSame(SovereigntyProfile::SelfHosted, $loaded->sovereigntyProfile());
            self::assertSame(['default_language' => 'en'], $loaded->get('wiki_schema'));
        });

        $fmt = self::formatTimeElementFromApiIso($loaded->createdAt()->toIso8601String());
        self::assertStringContainsString('datetime="', $fmt['html']);
        self::assertStringContainsString($fmt['datetime'], $fmt['html']);

        $instantiator = new EntityInstantiator(self::entityDefinition('community'));
        $again = $instantiator->instantiate(Community::class, $loaded->toArray());
        self::assertInstanceOf(Community::class, $again);
        self::assertSame($loaded->sovereigntyProfile(), $again->sovereigntyProfile());
    }

    #[Test]
    public function knowledge_item_row_hydrates_via_repository_instantiator_and_casts(): void
    {
        $uuid = Uuid::v4()->toRfc4122();
        $created = '2026-02-01T10:00:00+00:00';
        $compiled = '2026-02-02T12:00:00+00:00';

        $item = KnowledgeItem::make([
            'uuid'           => $uuid,
            'title'          => 'Treaty primer',
            'content'        => 'Body',
            'community_id'   => 'comm-int-1',
            'knowledge_type' => KnowledgeType::Governance->value,
            'access_tier'    => AccessTier::Staff->value,
            'created_at'     => $created,
            'compiled_at'    => $compiled,
            'allowed_roles'  => json_encode(['knowledge_keeper'], JSON_THROW_ON_ERROR),
            'allowed_users'  => '[]',
            'source_media_ids' => json_encode(['m1'], JSON_THROW_ON_ERROR),
        ]);
        $item->enforceIsNew(true);

        $repo = self::entityRepositoryFor('knowledge_item');
        $repo->save($item);

        $loaded = self::assertFirstByUuid($repo, $uuid);
        self::assertInstanceOf(KnowledgeItem::class, $loaded);

        $this->assertNoCastException(static function () use ($loaded): void {
            self::assertInstanceOf(CarbonImmutable::class, $loaded->createdAt());
            self::assertNull($loaded->updatedAt());
            self::assertSame(KnowledgeType::Governance, $loaded->getKnowledgeType());
            self::assertSame(AccessTier::Staff, $loaded->getAccessTier());
            self::assertSame(['knowledge_keeper'], $loaded->getAllowedRoles());
            self::assertSame([], $loaded->getAllowedUsers());
            self::assertSame(['m1'], $loaded->getSourceMediaIds());
        });

        $fmt = self::formatTimeElementFromApiIso($loaded->getCreatedAt());
        self::assertStringContainsString('datetime="', $fmt['html']);
        self::assertStringContainsString($fmt['datetime'], $fmt['html']);

        $instantiator = new EntityInstantiator(self::entityDefinition('knowledge_item'));
        $rawAgain = $instantiator->instantiate(KnowledgeItem::class, $loaded->toArray());
        self::assertInstanceOf(KnowledgeItem::class, $rawAgain);
        self::assertSame($loaded->getAccessTier(), $rawAgain->getAccessTier());
    }

    #[Test]
    public function wiki_lint_report_row_hydrates_via_repository_and_casts(): void
    {
        $uuid = Uuid::v4()->toRfc4122();
        $created = '2026-04-05T08:00:00+00:00';
        $findings = [
            ['item_id' => 'k1', 'type' => 'broken_link', 'message' => 'bad'],
        ];

        $report = WikiLintReport::make([
            'uuid'         => $uuid,
            'title'        => 'Lint run',
            'community_id' => 'comm-int-lint',
            'created_at'   => $created,
            'findings'     => $findings,
        ]);
        $report->enforceIsNew(true);

        $repo = self::entityRepositoryFor('wiki_lint_report');
        $repo->save($report);

        $loaded = self::assertFirstByUuid($repo, $uuid);
        self::assertInstanceOf(WikiLintReport::class, $loaded);

        $this->assertNoCastException(static function () use ($loaded, $findings): void {
            self::assertInstanceOf(CarbonImmutable::class, $loaded->createdAt());
            self::assertSame($findings, $loaded->getFindings());
        });

        $fmt = self::formatTimeElementFromApiIso($loaded->createdAt()->toIso8601String());
        self::assertStringContainsString('datetime="', $fmt['html']);
        self::assertStringContainsString($fmt['datetime'], $fmt['html']);

        $instantiator = new EntityInstantiator(self::entityDefinition('wiki_lint_report'));
        $again = $instantiator->instantiate(WikiLintReport::class, $loaded->toArray());
        self::assertInstanceOf(WikiLintReport::class, $again);
        self::assertSame($findings, $again->getFindings());
        self::assertTrue($loaded->createdAt()->eq($again->createdAt()));
    }

    #[Test]
    public function set_updated_at_on_knowledge_item_writes_iso8601_storage_shape(): void
    {
        $item = KnowledgeItem::make([
            'uuid'         => Uuid::v4()->toRfc4122(),
            'title'        => 'T',
            'content'      => 'B',
            'community_id' => 'c-upd',
        ]);
        $item->enforceIsNew(true);
        $at = CarbonImmutable::parse('2026-06-15T14:45:30+00:00');
        $item->set('updated_at', $at);

        $raw = $item->toArray();
        self::assertIsString($raw['updated_at'] ?? null);
        self::assertStringContainsString('2026-06-15', (string) $raw['updated_at']);
        self::assertInstanceOf(CarbonImmutable::class, $item->updatedAt());
    }

    #[Test]
    public function set_updated_at_on_community_writes_iso8601_storage_shape(): void
    {
        $entity = Community::make([
            'uuid' => Uuid::v4()->toRfc4122(),
            'name' => 'Upd Co',
            'slug' => 'upd-co',
        ]);
        $entity->enforceIsNew(true);
        $at = CarbonImmutable::parse('2026-06-16T09:20:00+00:00');
        $entity->set('updated_at', $at);

        $raw = $entity->toArray();
        self::assertIsString($raw['updated_at'] ?? null);
        self::assertStringContainsString('2026-06-16', (string) $raw['updated_at']);
        self::assertInstanceOf(CarbonImmutable::class, $entity->updatedAt());
    }

    #[Test]
    public function set_updated_at_on_wiki_lint_report_writes_iso8601_storage_shape(): void
    {
        $report = WikiLintReport::make([
            'uuid'         => Uuid::v4()->toRfc4122(),
            'title'        => 'W',
            'community_id' => 'c-w-upd',
        ]);
        $report->enforceIsNew(true);
        $at = CarbonImmutable::parse('2026-06-17T10:05:00+00:00');
        $report->set('updated_at', $at);

        $raw = $report->toArray();
        self::assertIsString($raw['updated_at'] ?? null);
        self::assertStringContainsString('2026-06-17', (string) $raw['updated_at']);
        self::assertInstanceOf(CarbonImmutable::class, $report->updatedAt());
    }

    #[Test]
    public function community_raw_sql_invalid_sovereignty_column_loads_with_local_fallback(): void
    {
        $db = self::database();
        self::assertInstanceOf(DBALDatabase::class, $db);
        $conn = $db->getConnection();
        $uuid = Uuid::v4()->toRfc4122();

        $conn->executeStatement(
            'INSERT INTO community (uuid, bundle, name, langcode, _data, slug, wiki_schema, locale, created_at, updated_at, sovereignty_profile, contact_email)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $uuid,
                'community',
                'Raw SQL Nation',
                'en',
                '{}',
                'raw-sql-nation',
                '{}',
                'en',
                '2026-01-01T00:00:00+00:00',
                '',
                'not_a_real_profile',
                '',
            ],
        );
        $id = (string) $conn->lastInsertId();

        $repo = self::entityRepositoryFor('community');
        $loaded = $repo->find($id);
        self::assertInstanceOf(Community::class, $loaded);
        self::assertSame(SovereigntyProfile::Local, $loaded->sovereigntyProfile());
    }

    #[Test]
    public function knowledge_item_raw_sql_bad_access_tier_normalizes_to_members(): void
    {
        $db = self::database();
        self::assertInstanceOf(DBALDatabase::class, $db);
        $conn = $db->getConnection();
        $uuid = Uuid::v4()->toRfc4122();

        $conn->executeStatement(
            'INSERT INTO knowledge_item (uuid, bundle, title, langcode, _data, community_id, content, knowledge_type, access_tier, created_at, updated_at, compiled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $uuid,
                'knowledge_item',
                'Item',
                'en',
                '{}',
                'c-raw',
                'X',
                '',
                'nonexistent_tier',
                '2026-01-02T00:00:00+00:00',
                '',
                '',
            ],
        );
        $id = (string) $conn->lastInsertId();

        $loaded = self::entityRepositoryFor('knowledge_item')->find($id);
        self::assertInstanceOf(KnowledgeItem::class, $loaded);
        self::assertSame(AccessTier::Members, $loaded->getAccessTier());
    }

    #[Test]
    public function knowledge_item_raw_sql_invalid_knowledge_type_yields_null_type(): void
    {
        $db = self::database();
        self::assertInstanceOf(DBALDatabase::class, $db);
        $conn = $db->getConnection();
        $uuid = Uuid::v4()->toRfc4122();

        $conn->executeStatement(
            'INSERT INTO knowledge_item (uuid, bundle, title, langcode, _data, community_id, content, knowledge_type, access_tier, created_at, updated_at, compiled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $uuid,
                'knowledge_item',
                'Item',
                'en',
                '{}',
                'c-raw-2',
                'X',
                'bogus_type',
                'public',
                '2026-01-02T00:00:00+00:00',
                '',
                '',
            ],
        );
        $id = (string) $conn->lastInsertId();

        $loaded = self::entityRepositoryFor('knowledge_item')->find($id);
        self::assertInstanceOf(KnowledgeItem::class, $loaded);
        self::assertNull($loaded->getKnowledgeType());
    }

    #[Test]
    public function wiki_lint_report_raw_sql_corrupt_findings_column_normalizes_on_load(): void
    {
        $db = self::database();
        self::assertInstanceOf(DBALDatabase::class, $db);
        $conn = $db->getConnection();
        $uuid = Uuid::v4()->toRfc4122();

        $conn->executeStatement(
            'INSERT INTO wiki_lint_report (uuid, bundle, title, langcode, _data, community_id, created_at, updated_at, findings)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $uuid,
                'wiki_lint_report',
                'Raw findings',
                'en',
                '{}',
                'c-raw-findings',
                '2026-01-03T00:00:00+00:00',
                '',
                '{not valid json',
            ],
        );
        $id = (string) $conn->lastInsertId();

        $loaded = self::entityRepositoryFor('wiki_lint_report')->find($id);
        self::assertInstanceOf(WikiLintReport::class, $loaded);
        $this->assertNoCastException(static function () use ($loaded): void {
            self::assertSame([], $loaded->getFindings());
            $loaded->get('findings');
        });
    }

    #[Test]
    public function wiki_lint_corrupt_findings_json_string_normalizes_to_empty_list(): void
    {
        $report = WikiLintReport::make([
            'uuid'         => Uuid::v4()->toRfc4122(),
            'title'        => 'Bad findings',
            'community_id' => 'c1',
            'findings'     => '{not json',
        ]);
        self::assertSame([], $report->getFindings());
        $this->assertNoCastException(static fn () => $report->get('findings'));
    }

    #[Test]
    public function duplicate_and_with_round_trip_without_argument_count_errors(): void
    {
        // Waaseyaa EntityBase::with(string $name, mixed $value) — no associative-array overload.
        $community = Community::make([
            'uuid'   => Uuid::v4()->toRfc4122(),
            'name'   => 'Dup Co',
            'slug'   => 'dup-co',
            'locale' => 'en',
        ]);
        $d = $community->duplicate();
        self::assertInstanceOf(Community::class, $d);
        $w = $d->with('contact_email', 'x@y.z');
        self::assertSame('x@y.z', $w->contactEmail());
        self::assertSame(SovereigntyProfile::Local, $w->sovereigntyProfile());
        self::assertInstanceOf(CarbonImmutable::class, $w->createdAt());

        $item = KnowledgeItem::make([
            'uuid'         => Uuid::v4()->toRfc4122(),
            'title'        => 'Dup item',
            'content'      => 'C',
            'community_id' => 'c-dup',
            'access_tier'  => AccessTier::Public->value,
        ]);
        $d2 = $item->duplicate();
        self::assertInstanceOf(KnowledgeItem::class, $d2);
        $w2 = $d2->with('content', 'Updated');
        self::assertSame('Updated', $w2->getContent());
        self::assertSame(AccessTier::Public, $w2->getAccessTier());
        self::assertInstanceOf(CarbonImmutable::class, $w2->createdAt());

        $report = WikiLintReport::make([
            'uuid'         => Uuid::v4()->toRfc4122(),
            'title'        => 'R',
            'community_id' => 'c-r',
            'findings'     => [],
        ]);
        $d3 = $report->duplicate();
        self::assertInstanceOf(WikiLintReport::class, $d3);
        $w3 = $d3->with('title', 'R2');
        self::assertSame('R2', (string) $w3->get('title'));
        self::assertSame([], $w3->getFindings());
        self::assertInstanceOf(CarbonImmutable::class, $w3->createdAt());
    }

    #[Test]
    public function make_and_from_storage_produce_identical_storage_bags(): void
    {
        $cBag = [
            'uuid'                => Uuid::v4()->toRfc4122(),
            'name'                => 'Bag Co',
            'slug'                => 'bag-co',
            'sovereignty_profile' => SovereigntyProfile::Northops->value,
            'locale'              => 'fr',
            'created_at'          => '2026-05-01T12:00:00+00:00',
        ];
        $ctxC = new HydrationContext(
            entityTypeId: 'community',
            entityKeys: self::entityDefinition('community')->getKeys(),
        );
        self::assertStorageBagsEqualIgnoringGeneratedKeys(
            Community::make($cBag)->toArray(),
            Community::fromStorage($cBag, $ctxC)->toArray(),
        );

        $kBag = [
            'uuid'          => Uuid::v4()->toRfc4122(),
            'title'         => 'K',
            'content'       => 'Body',
            'community_id'  => 'c-bag',
            'access_tier'   => AccessTier::Members->value,
            'knowledge_type'=> KnowledgeType::Land->value,
            'created_at'    => '2026-05-02T12:00:00+00:00',
        ];
        $ctxK = new HydrationContext(
            entityTypeId: 'knowledge_item',
            entityKeys: self::entityDefinition('knowledge_item')->getKeys(),
        );
        self::assertStorageBagsEqualIgnoringGeneratedKeys(
            KnowledgeItem::make($kBag)->toArray(),
            KnowledgeItem::fromStorage($kBag, $ctxK)->toArray(),
        );

        $wBag = [
            'uuid'         => Uuid::v4()->toRfc4122(),
            'title'        => 'W',
            'community_id' => 'c-w',
            'findings'     => [['item_id' => '1', 'type' => 't', 'message' => 'm']],
            'created_at'   => '2026-05-03T12:00:00+00:00',
        ];
        $ctxW = new HydrationContext(
            entityTypeId: 'wiki_lint_report',
            entityKeys: self::entityDefinition('wiki_lint_report')->getKeys(),
        );
        self::assertStorageBagsEqualIgnoringGeneratedKeys(
            WikiLintReport::make($wBag)->toArray(),
            WikiLintReport::fromStorage($wBag, $ctxW)->toArray(),
        );
    }

    #[Test]
    public function repository_save_load_round_trips_all_three_entities(): void
    {
        $uuidC = Uuid::v4()->toRfc4122();
        $community = Community::make([
            'uuid'                => $uuidC,
            'name'                => 'Round Co',
            'slug'                => 'round-co',
            'sovereignty_profile' => SovereigntyProfile::Local->value,
            'wiki_schema'         => ['default_language' => 'en'],
            'created_at'          => '2026-07-01T10:00:00+00:00',
            'updated_at'          => '2026-07-02T11:00:00+00:00',
            'contact_email'       => 'co@example.org',
        ]);
        $community->enforceIsNew(true);
        $cRepo = self::entityRepositoryFor('community');
        $cRepo->save($community);
        $cLoad = self::assertFirstByUuid($cRepo, $uuidC);
        self::assertInstanceOf(Community::class, $cLoad);
        self::assertSame('Round Co', $cLoad->name());
        self::assertSame(SovereigntyProfile::Local, $cLoad->sovereigntyProfile());
        self::assertSame(['default_language' => 'en'], $cLoad->get('wiki_schema'));
        self::assertInstanceOf(CarbonImmutable::class, $cLoad->createdAt());

        $cBag = $cLoad->toArray();
        self::assertSame(SovereigntyProfile::Local->value, $cBag['sovereignty_profile']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', (string) $cBag['created_at']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', (string) $cBag['updated_at']);

        $commPk = (string) $cLoad->get('id');

        $uuidK = Uuid::v4()->toRfc4122();
        $item = KnowledgeItem::make([
            'uuid'           => $uuidK,
            'title'          => 'Round item',
            'content'        => 'Hello',
            'community_id'   => $commPk,
            'knowledge_type' => KnowledgeType::Cultural->value,
            'access_tier'    => AccessTier::Restricted->value,
            'created_at'     => '2026-07-03T09:00:00+00:00',
            'allowed_roles'  => ['knowledge_keeper'],
            'allowed_users'  => ['u1'],
            'source_media_ids' => ['s1'],
        ]);
        $item->enforceIsNew(true);
        $kItemRepo = self::giikenProvider()->resolve(KnowledgeItemRepositoryInterface::class);
        $kItemRepo->save($item);
        $kRepo = self::entityRepositoryFor('knowledge_item');
        $iLoad = self::assertFirstByUuid($kRepo, $uuidK);
        self::assertInstanceOf(KnowledgeItem::class, $iLoad);
        self::assertSame('Round item', $iLoad->getTitle());
        self::assertSame($commPk, $iLoad->getCommunityId());
        self::assertSame(KnowledgeType::Cultural, $iLoad->getKnowledgeType());
        self::assertSame(AccessTier::Restricted, $iLoad->getAccessTier());
        self::assertSame(['knowledge_keeper'], $iLoad->getAllowedRoles());
        self::assertSame(['u1'], $iLoad->getAllowedUsers());
        self::assertSame(['s1'], $iLoad->getSourceMediaIds());
        self::assertInstanceOf(CarbonImmutable::class, $iLoad->createdAt());
        self::assertNotNull($iLoad->updatedAt());

        $kBag = $iLoad->toArray();
        self::assertSame(AccessTier::Restricted->value, $kBag['access_tier']);
        self::assertSame(KnowledgeType::Cultural->value, $kBag['knowledge_type']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', (string) $kBag['created_at']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', (string) $kBag['updated_at']);
        self::assertSame(['knowledge_keeper'], json_decode((string) $kBag['allowed_roles'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['u1'], json_decode((string) $kBag['allowed_users'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['s1'], json_decode((string) $kBag['source_media_ids'], true, 512, JSON_THROW_ON_ERROR));

        $kid = (string) $iLoad->id();

        $uuidW = Uuid::v4()->toRfc4122();
        $report = WikiLintReport::make([
            'uuid'         => $uuidW,
            'title'        => 'Round lint',
            'community_id' => $commPk,
            'created_at'   => '2026-07-04T08:00:00+00:00',
            'findings'     => [
                ['item_id' => $kid, 'type' => 'orphan', 'message' => 'm'],
            ],
        ]);
        $report->enforceIsNew(true);
        $wRepo = self::entityRepositoryFor('wiki_lint_report');
        $wRepo->save($report);
        $wLoad = self::assertFirstByUuid($wRepo, $uuidW);
        self::assertInstanceOf(WikiLintReport::class, $wLoad);
        self::assertSame($commPk, $wLoad->getCommunityId());
        self::assertCount(1, $wLoad->getFindings());
        self::assertSame('orphan', $wLoad->getFindings()[0]['type']);

        $wBag = $wLoad->toArray();
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', (string) $wBag['created_at']);
        $decodedFindings = json_decode((string) $wBag['findings'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decodedFindings);
        self::assertSame('orphan', $decodedFindings[0]['type'] ?? null);
    }

    /**
     * @param callable(): void $fn
     */
    private function assertNoCastException(callable $fn): void
    {
        try {
            $fn();
        } catch (\Waaseyaa\Entity\Cast\Exception\CastException $e) {
            self::fail('Unexpected CastException: ' . $e->getMessage());
        }
    }
}
