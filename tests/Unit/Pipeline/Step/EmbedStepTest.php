<?php
declare(strict_types=1);
namespace App\Tests\Unit\Pipeline\Step;

use App\Entity\KnowledgeItem\KnowledgeItem;
use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Pipeline\CompilationPayload;
use App\Pipeline\Provider\EmbeddingProviderInterface;
use App\Pipeline\Step\EmbedStep;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Pipeline\PipelineContext;

#[CoversClass(EmbedStep::class)]
final class EmbedStepTest extends TestCase
{
    #[Test]
    public function it_creates_knowledge_item_and_stores_embedding(): void
    {
        $storedEntityId = null;
        $storedText = null;
        $storedCommunityId = null;

        $embeddings = new class($storedEntityId, $storedText, $storedCommunityId) implements EmbeddingProviderInterface {
            /** @phpstan-ignore-next-line */
            public function __construct(private ?string &$storedEntityId, private ?string &$storedText, private ?string &$storedCommunityId) {}
            public function embed(string $text): array { return [0.1]; }
            public function search(string $query, string $communityId, int $limit = 5): array { return []; }
            public function store(string $entityId, string $text, string $communityId): void
            {
                $this->storedEntityId = $entityId;
                $this->storedText = $text;
                $this->storedCommunityId = $communityId;
            }
        };

        $savedItem = null;
        $repo = new class($savedItem) implements KnowledgeItemRepositoryInterface {
            /** @phpstan-ignore-next-line */
            public function __construct(private ?KnowledgeItem &$savedItem) {}
            public function find(string $id): ?KnowledgeItem { return null; }
            public function findByCommunity(string $communityId): array { return []; }
            public function save(KnowledgeItem $item): void { $this->savedItem = $item; }
            public function delete(KnowledgeItem $item): void {}
        };

        $step = new EmbedStep($embeddings, $repo);
        $payload = new CompilationPayload();
        $payload->communityId = 'comm-1';
        $payload->title = 'Solar Update';
        $payload->content = "# Solar Update\n\nDetails here.";
        $payload->summary = 'A summary.';
        $payload->mediaId = 'media-1';

        $context = new PipelineContext(pipelineId: 'test', startedAt: time());
        $result = $step->process(['payload' => $payload], $context);

        $this->assertTrue($result->success);
        if (!$savedItem instanceof KnowledgeItem) {
            self::fail('Expected saved item to be a KnowledgeItem.');
        }
        $saved = $savedItem;
        $this->assertSame('Solar Update', $saved->getTitle());
        if (!is_string($storedCommunityId)) {
            self::fail('Expected stored community id to be a string.');
        }
        $communityId = $storedCommunityId;
        $this->assertSame('comm-1', $communityId);
        if (!is_string($storedText)) {
            self::fail('Expected stored embedding text to be a string.');
        }
        $embeddingText = $storedText;
        $this->assertStringContainsString('Solar Update', $embeddingText);

        // Entity ID used for embedding must match the UUID on the saved item
        if (!is_string($storedEntityId)) {
            self::fail('Expected stored entity id to be a string.');
        }
        $this->assertSame((string) $saved->get('uuid'), $storedEntityId);
    }

    #[Test]
    public function it_describes_itself(): void
    {
        $embeddings = new class implements EmbeddingProviderInterface {
            public function embed(string $text): array { return []; }
            public function search(string $query, string $communityId, int $limit = 5): array { return []; }
            public function store(string $entityId, string $text, string $communityId): void {}
        };
        $repo = new class implements KnowledgeItemRepositoryInterface {
            public function find(string $id): ?KnowledgeItem { return null; }
            public function findByCommunity(string $communityId): array { return []; }
            public function save(KnowledgeItem $item): void {}
            public function delete(KnowledgeItem $item): void {}
        };

        $step = new EmbedStep($embeddings, $repo);
        $this->assertSame('Generate vector embedding and persist KnowledgeItem', $step->describe());
    }
}
