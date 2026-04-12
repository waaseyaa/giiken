<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Entity\Community\Community;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Entity\Community\WikiSchema;
use App\Entity\KnowledgeItem\AccessTier;
use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Entity\KnowledgeItem\KnowledgeType;
use App\Provider\AppServiceProvider;
use App\Tests\Integration\Support\AppKernelIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

#[CoversNothing]
final class SearchPageTest extends AppKernelIntegrationTestCase
{
    private static bool $seeded = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$seeded) {
            return;
        }

        /** @var AppServiceProvider $giiken */
        $giiken = self::giikenProvider();

        /** @var CommunityRepositoryInterface $communityRepo */
        $communityRepo = $giiken->resolve(CommunityRepositoryInterface::class);
        /** @var KnowledgeItemRepositoryInterface $itemRepo */
        $itemRepo = $giiken->resolve(KnowledgeItemRepositoryInterface::class);

        $wiki = new WikiSchema(
            defaultLanguage: 'en',
            knowledgeTypes: ['cultural', 'governance', 'land'],
            llmInstructions: '',
        );
        $community = Community::make([
            'uuid'        => Uuid::v4()->toRfc4122(),
            'name'        => 'Test Community',
            'bundle'      => 'community',
            'slug'        => 'test-community',
            'wiki_schema' => $wiki->toArray(),
        ]);
        $community->enforceIsNew(true);
        $communityRepo->save($community);

        $community = $communityRepo->findBySlug('test-community');
        self::assertNotNull($community);
        $communityId = (string) $community->get('id');

        $samples = [
            ['title' => 'Welcome to Giiken', 'content' => 'This is a seeded knowledge item for local development.', 'type' => KnowledgeType::Cultural],
            ['title' => 'Governance overview', 'content' => 'Sample governance note. Public tier so it appears for anonymous visitors.', 'type' => KnowledgeType::Governance],
            ['title' => 'Land and territory', 'content' => 'Sample land reference. Use the ingestion pipeline to import real documents.', 'type' => KnowledgeType::Land],
        ];

        foreach ($samples as $row) {
            $item = KnowledgeItem::make([
                'uuid'          => Uuid::v4()->toRfc4122(),
                'title'         => $row['title'],
                'bundle'        => 'knowledge_item',
                'content'       => $row['content'],
                'community_id'  => $communityId,
                'knowledge_type' => $row['type']->value,
                'access_tier'   => AccessTier::Public->value,
            ]);
            $item->enforceIsNew(true);
            $itemRepo->save($item);
        }

        self::$seeded = true;
    }

    public static function tearDownAfterClass(): void
    {
        self::$seeded = false;
        parent::tearDownAfterClass();
    }

    #[Test]
    public function search_with_governance_query_returns_only_governance_item(): void
    {
        $decoded = $this->handleInertiaRequest('/test-community/search?q=governance');

        self::assertSame('Discovery/Search', $decoded['component'] ?? null);
        $props = $decoded['props'] ?? [];
        self::assertSame('governance', $props['query'] ?? null);
        self::assertSame(1, $props['results']['totalHits'] ?? null);

        $items = $props['results']['items'] ?? [];
        self::assertCount(1, $items);
        self::assertSame('Governance overview', $items[0]['title'] ?? null);
    }

    #[Test]
    public function search_with_no_query_returns_all_items(): void
    {
        $decoded = $this->handleInertiaRequest('/test-community/search');

        $props = $decoded['props'] ?? [];
        self::assertSame('', $props['query'] ?? null);
        self::assertSame(3, $props['results']['totalHits'] ?? null);
        self::assertCount(3, $props['results']['items'] ?? []);
    }

    #[Test]
    public function search_with_zero_match_query_returns_empty_result_set(): void
    {
        $decoded = $this->handleInertiaRequest('/test-community/search?q=zzznoresults');

        $props = $decoded['props'] ?? [];
        self::assertSame('zzznoresults', $props['query'] ?? null);
        self::assertSame(0, $props['results']['totalHits'] ?? null);
        self::assertSame([], $props['results']['items'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleInertiaRequest(string $uri): array
    {
        $saved = $_SERVER;
        $queryString = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');
        $_SERVER = array_merge($saved, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $uri,
            'QUERY_STRING' => $queryString,
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/index.php',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTP_X_INERTIA' => 'true',
            'HTTP_X_INERTIA_VERSION' => 'giiken',
        ]);

        $savedGet = $_GET;
        parse_str($queryString, $parsed);
        /** @var array<string, mixed> $parsed */
        $_GET = $parsed;

        try {
            $response = self::kernel()->handle();
            self::assertInstanceOf(Response::class, $response);
            self::assertSame(200, $response->getStatusCode(), 'Inertia search endpoint should return 200');

            $content = (string) $response->getContent();
            $decoded = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);

            return $decoded;
        } finally {
            $_SERVER = $saved;
            $_GET = $savedGet;
        }
    }
}
