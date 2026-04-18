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
final class ShowPageTest extends AppKernelIntegrationTestCase
{
    private static bool $seeded = false;
    private static string $testCommunityItemId = '';
    private static string $otherCommunityItemId = '';

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

        $testCommunity = self::createCommunity($communityRepo, 'Test Community', 'test-community');
        $otherCommunity = self::createCommunity($communityRepo, 'Other Community', 'other-community');

        self::$testCommunityItemId = self::createItem(
            $itemRepo,
            (string) $testCommunity->get('id'),
            'Visible item',
        );
        self::$otherCommunityItemId = self::createItem(
            $itemRepo,
            (string) $otherCommunity->get('id'),
            'Leaked item',
        );

        self::$seeded = true;
    }

    public static function tearDownAfterClass(): void
    {
        self::$seeded = false;
        self::$testCommunityItemId = '';
        self::$otherCommunityItemId = '';

        parent::tearDownAfterClass();
    }

    #[Test]
    public function show_returns_item_when_it_belongs_to_requested_community(): void
    {
        $decoded = $this->handleInertiaRequest('/test-community/item/' . self::$testCommunityItemId);

        self::assertSame('Discovery/Show', $decoded['component'] ?? null);
        self::assertSame('Visible item', $decoded['props']['item']['title'] ?? null);
    }

    #[Test]
    public function show_does_not_leak_item_from_other_community(): void
    {
        $decoded = $this->handleInertiaRequest('/test-community/item/' . self::$otherCommunityItemId);

        self::assertSame('Discovery/Show', $decoded['component'] ?? null);
        self::assertNull($decoded['props']['item'] ?? null);
    }

    private static function createCommunity(
        CommunityRepositoryInterface $communityRepo,
        string $name,
        string $slug,
    ): Community {
        $wiki = new WikiSchema(
            defaultLanguage: 'en',
            knowledgeTypes: ['cultural', 'governance', 'land'],
            llmInstructions: '',
        );

        $community = Community::make([
            'uuid' => Uuid::v4()->toRfc4122(),
            'name' => $name,
            'bundle' => 'community',
            'slug' => $slug,
            'wiki_schema' => $wiki->toArray(),
        ]);
        $community->enforceIsNew(true);
        $communityRepo->save($community);

        $loaded = $communityRepo->findBySlug($slug);
        self::assertNotNull($loaded);

        return $loaded;
    }

    private static function createItem(
        KnowledgeItemRepositoryInterface $itemRepo,
        string $communityId,
        string $title,
    ): string {
        $uuid = Uuid::v4()->toRfc4122();
        $item = KnowledgeItem::make([
            'uuid' => $uuid,
            'title' => $title,
            'bundle' => 'knowledge_item',
            'content' => $title . ' content.',
            'community_id' => $communityId,
            'knowledge_type' => KnowledgeType::Cultural->value,
            'access_tier' => AccessTier::Public->value,
        ]);
        $item->enforceIsNew(true);
        $itemRepo->save($item);

        $loaded = self::assertFirstByUuid(self::entityRepositoryFor('knowledge_item'), $uuid);

        return (string) $loaded->id();
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
            self::assertSame(200, $response->getStatusCode(), 'Inertia show endpoint should return 200');

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
