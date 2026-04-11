<?php

declare(strict_types=1);

namespace Giiken\Tests\Integration\Http;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\Community\WikiSchema;
use Giiken\Entity\KnowledgeItem\AccessTier;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeType;
use Giiken\GiikenServiceProvider;
use Giiken\Tests\Integration\Support\GiikenKernelIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

#[CoversNothing]
final class AskPageTest extends GiikenKernelIntegrationTestCase
{
    private static bool $seeded = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$seeded) {
            return;
        }

        /** @var GiikenServiceProvider $giiken */
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
            ['title' => 'Land and territory', 'content' => 'Sample land reference.', 'type' => KnowledgeType::Land],
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
    public function ask_reflects_user_question_from_q_query_string(): void
    {
        $decoded = $this->handleInertiaRequest('/test-community/ask?q=what+is+governance+about+here');

        self::assertSame('Discovery/Ask', $decoded['component'] ?? null);
        $props = $decoded['props'] ?? [];
        self::assertSame('what is governance about here', $props['question'] ?? null);
        self::assertIsString($props['answer'] ?? null);
        // Multi-word tokenization (see #61) should surface "governance" and
        // return at least one related item + citation from the seeded
        // Governance overview. Real answer content is still from the stub
        // LLM provider (#59), so we do not assert answer text.
        self::assertGreaterThan(0, $props['relatedItems']['totalHits'] ?? 0);
        self::assertFalse($props['noRelevantItems'] ?? null);
        self::assertGreaterThan(0, count($props['citations'] ?? []));
    }

    #[Test]
    public function ask_with_no_q_returns_empty_question(): void
    {
        $decoded = $this->handleInertiaRequest('/test-community/ask');

        $props = $decoded['props'] ?? [];
        self::assertSame('', $props['question'] ?? null);
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
            self::assertSame(200, $response->getStatusCode(), 'Inertia ask endpoint should return 200');

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
